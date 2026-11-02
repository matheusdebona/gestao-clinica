<?php

namespace App\Support\Demo;

use App\Models\User;
use App\Services\CommissionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Apaga somente o que o manifesto lista (sempre filtrando pela clínica) e devolve o estoque/custo
 * dos produtos que já existiam antes do seed.
 */
final class DemoClinicPurger
{
    /**
     * Linhas que o próprio app pode criar DEPOIS do seed a partir de registros da demo
     * (ex.: despesa recorrente gerada pelo cron, comissão recalculada, venda nova para paciente demo).
     * Também são apagadas, sempre dentro da clínica.
     */
    private const DERIVED = [
        'commission_entries' => ['sale_id' => 'sales', 'appointment_id' => 'appointments', 'lead_id' => 'leads', 'user_id' => 'users'],
        'expenses' => ['recurring_expense_id' => 'recurring_expenses', 'equipment_asset_id' => 'equipment_assets', 'campaign_id' => 'campaigns'],
        'patient_returns' => ['client_id' => 'clients'],
        'documents' => ['client_id' => 'clients', 'sale_id' => 'sales', 'budget_id' => 'budgets'],
        'appointments' => ['client_id' => 'clients'],
        'treatments' => ['client_id' => 'clients'],
        'sales' => ['client_id' => 'clients', 'sold_by_user_id' => 'users'],
        'budgets' => ['client_id' => 'clients', 'created_by_user_id' => 'users'],
        'lead_daily_checkins' => ['user_id' => 'users'],
        'user_goals' => ['user_id' => 'users'],
        'remuneration_tiers' => ['user_id' => 'users'],
    ];

    /** @var array<string, int> */
    public array $deleted = [];

    public function __construct(private readonly int $clinicId) {}

    /**
     * @param  array{meta: array<string, mixed>, created: array<string, list<int>>}  $manifest
     */
    public function purge(array $manifest): void
    {
        $created = $this->withDerived($manifest['created']);
        $this->restorePreexistingProducts($manifest['meta']['preexisting_products'] ?? [], $created['stock_movements'] ?? []);

        $userIds = $created['users'] ?? [];
        if ($userIds !== []) {
            DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $userIds)->delete();
            DB::table('model_has_permissions')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $userIds)->delete();
            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->where('notifiable_type', (new User)->getMorphClass())->whereIn('notifiable_id', $userIds)->delete();
            }
            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $userIds)->delete();
            }
        }
        if (isset($created['clients']) && Schema::hasColumn('clients', 'lead_id')) {
            DB::table('clients')->where('clinic_id', $this->clinicId)->whereIn('id', $created['clients'])->update(['lead_id' => null]);
        }

        // Várias passadas: cada tabela é apagada num savepoint; se uma FK ainda segura, tenta de novo depois.
        $pending = array_filter($created, fn (array $ids) => $ids !== []);
        for ($pass = 0; $pass < 25 && $pending !== []; $pass++) {
            foreach (array_keys($pending) as $table) {
                try {
                    DB::transaction(function () use ($table, $pending): void {
                        $count = 0;
                        foreach (array_chunk($pending[$table], 500) as $chunk) {
                            $count += $this->scoped($table)->whereIn("{$table}.id", $chunk)->delete();
                        }
                        $this->deleted[$table] = ($this->deleted[$table] ?? 0) + $count;
                    });
                    unset($pending[$table]);
                } catch (QueryException) {
                    // FK ainda referenciada por outra tabela do manifesto; próxima passada.
                }
            }
        }
        if ($pending !== []) {
            throw new RuntimeException('Não consegui apagar (FK de dados que não são do seed?): '.implode(', ', array_keys($pending)));
        }

        foreach ($manifest['meta']['months'] ?? [] as $month) {
            $m = CarbonImmutable::parse($month.'-01', 'America/Sao_Paulo');
            if ($m->format('Y-m') <= CarbonImmutable::now('America/Sao_Paulo')->format('Y-m')) {
                app(CommissionService::class)->rebuildMonth($this->clinicId, $m);
            }
        }
    }

    /**
     * @param  array<string, list<int>>  $created
     * @return array<string, list<int>>
     */
    private function withDerived(array $created): array
    {
        foreach (self::DERIVED as $table => $links) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($links as $column => $parent) {
                $parentIds = $created[$parent] ?? [];
                if ($parentIds === [] || ! Schema::hasColumn($table, $column)) {
                    continue;
                }
                foreach (array_chunk($parentIds, 500) as $chunk) {
                    $ids = DB::table($table)->where('clinic_id', $this->clinicId)->whereIn($column, $chunk)->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $created[$table] = array_values(array_unique(array_merge($created[$table] ?? [], $ids)));
                }
            }
        }

        return $created;
    }

    /** Query de delete restrita à clínica (direto pelo clinic_id ou pelo registro pai). */
    private function scoped(string $table)
    {
        if (Schema::hasColumn($table, 'clinic_id')) {
            return DB::table($table)->where('clinic_id', $this->clinicId);
        }
        $parents = DemoClinicGuard::parentOf($table);
        if ($parents === null) {
            throw new RuntimeException("Tabela {$table} sem clinic_id nem pai conhecido; não apago às cegas.");
        }
        [$parent, $fk] = $parents;

        return DB::table($table)->whereIn($fk, DB::table($parent)->where('clinic_id', $this->clinicId)->select('id'));
    }

    /**
     * @param  array<int|string, array{stock: string, cost: string}>  $snapshot
     * @param  list<int>  $demoMovementIds
     */
    private function restorePreexistingProducts(array $snapshot, array $demoMovementIds): void
    {
        foreach ($snapshot as $productId => $before) {
            $product = DB::table('products')->where('clinic_id', $this->clinicId)->where('id', (int) $productId)->first();
            if ($product === null) {
                continue;
            }
            $demo = DB::table('stock_movements')->where('product_id', $product->id)->whereIn('id', $demoMovementIds ?: [0]);
            $net = (float) (clone $demo)->sum(DB::raw("CASE WHEN type = 'in' THEN quantity ELSE -quantity END"));
            $firstDemo = (clone $demo)->min('id');
            $laterReal = $firstDemo !== null && DB::table('stock_movements')->where('product_id', $product->id)
                ->where('id', '>', $firstDemo)->whereNotIn('id', $demoMovementIds ?: [0])->exists();
            DB::table('products')->where('id', $product->id)->update([
                'stock_quantity' => number_format((float) $product->stock_quantity - $net, 4, '.', ''),
                'cost' => $laterReal ? $product->cost : $before['cost'],
            ]);
        }
    }
}
