<?php

namespace App\Support\Demo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Impressão digital (contagem + último updated_at) de tudo que pertence às OUTRAS clínicas.
 * O seed e o purge comparam antes/depois e abortam (rollback) se qualquer número mudar.
 */
final class DemoClinicGuard
{
    /** Tabelas sem clinic_id, ligadas à clínica pelo pai. */
    private const CHILDREN = [
        'sale_items' => ['sales', 'sale_id'],
        'sale_payments' => ['sales', 'sale_id'],
        'budget_items' => ['budgets', 'budget_id'],
        'lead_activities' => ['leads', 'lead_id'],
        'appointment_consumptions' => ['appointments', 'appointment_id'],
        'protocol_items' => ['protocols', 'protocol_id'],
    ];

    /** Tabelas globais que o seed não deve tocar. */
    private const GLOBAL = ['jobs', 'failed_jobs', 'clinics', 'roles', 'permissions', 'role_has_permissions'];

    /** @return array{0: string, 1: string}|null */
    public static function parentOf(string $table): ?array
    {
        return self::CHILDREN[$table] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function clinicTables(): array
    {
        $tables = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            $schema = $table['schema'] ?? null;
            if ($schema !== null && ! in_array($schema, ['public', 'main'], true)) {
                continue;
            }
            if (Schema::hasColumn($name, 'clinic_id')) {
                $tables[] = $name;
            }
        }
        sort($tables);

        return $tables;
    }

    /**
     * @return array<string, string>
     */
    public static function fingerprintOthers(int $clinicId): array
    {
        $out = [];
        foreach (self::clinicTables() as $table) {
            $q = DB::table($table)->where(fn ($w) => $w->where('clinic_id', '!=', $clinicId)->orWhereNull('clinic_id'));
            $out[$table] = self::stamp($q, $table);
        }
        foreach (self::CHILDREN as $child => [$parent, $fk]) {
            $q = DB::table($child)->join($parent, "{$parent}.id", '=', "{$child}.{$fk}")
                ->where("{$parent}.clinic_id", '!=', $clinicId);
            $out[$child] = self::stamp($q, $child, "{$child}.");
        }
        $users = DB::table('users')->where(fn ($w) => $w->where('clinic_id', '!=', $clinicId)->orWhereNull('clinic_id'))->pluck('id');
        $out['model_has_roles'] = (string) DB::table('model_has_roles')->whereIn('model_id', $users)->count();
        $out['notifications'] = self::stamp(DB::table('notifications')->whereIn('notifiable_id', $users), 'notifications');
        foreach (self::GLOBAL as $table) {
            if (Schema::hasTable($table)) {
                $out['global:'.$table] = self::stamp(DB::table($table), $table);
            }
        }

        // clinics: a própria linha da clínica-alvo também não é alterada.
        return $out;
    }

    /**
     * @param  array<string, string>  $before
     */
    public static function assertUnchanged(array $before, int $clinicId): void
    {
        $after = self::fingerprintOthers($clinicId);
        $diff = [];
        foreach ($before as $key => $value) {
            if (($after[$key] ?? null) !== $value) {
                $diff[] = "{$key}: {$value} -> ".($after[$key] ?? 'null');
            }
        }
        if ($diff !== []) {
            throw new RuntimeException('Dados de outras clínicas mudaram (abortado, rollback): '.implode('; ', $diff));
        }
    }

    /**
     * @return array<int, array<string, int>> clinic_id => [table => count]
     */
    public static function countsByClinic(): array
    {
        $out = [];
        foreach (self::clinicTables() as $table) {
            foreach (DB::table($table)->selectRaw('clinic_id, count(*) as n')->groupBy('clinic_id')->get() as $row) {
                $out[(int) $row->clinic_id][$table] = (int) $row->n;
            }
        }

        return $out;
    }

    private static function stamp($query, string $table, string $prefix = ''): string
    {
        $hasUpdated = Schema::hasColumn($table, 'updated_at');
        $row = $query->selectRaw('count(*) as n'.($hasUpdated ? ", max({$prefix}updated_at) as u" : ''))->first();
        if ($row === null) {
            throw new RuntimeException("Falha ao ler {$table}");
        }

        return $row->n.($hasUpdated ? '|'.($row->u ?? '-') : '');
    }
}
