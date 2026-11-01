<?php

use App\Support\ClinicMonth;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Competência das comissões de venda e de sessão passa a ser o mês civil de São Paulo (antes era o
 * mês UTC: venda em 31/10 23:30 em São Paulo caía em novembro). Só lançamentos pendentes são
 * corrigidos; pagos e cancelados ficam como estão.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->fix('seller', 'sales', 'sale_id', 'sold_at');
        $this->fix('professional', 'appointments', 'appointment_id', 'finished_at');
    }

    public function down(): void
    {
        // Correção de dados: sem volta (o mês UTC era o erro).
    }

    private function fix(string $role, string $table, string $fk, string $column): void
    {
        DB::table('commission_entries')
            ->join($table, $table.'.id', '=', 'commission_entries.'.$fk)
            ->where('commission_entries.role', $role)
            ->where('commission_entries.status', 'pending')
            ->whereNotNull($table.'.'.$column)
            ->select('commission_entries.id', 'commission_entries.competence_month', $table.'.'.$column.' as occurred_at')
            ->orderBy('commission_entries.id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    // Datas gravadas em UTC (timezone da app).
                    $competence = ClinicMonth::competence(CarbonImmutable::parse($row->occurred_at, 'UTC'));
                    if (substr((string) $row->competence_month, 0, 10) !== $competence) {
                        DB::table('commission_entries')->where('id', $row->id)->update(['competence_month' => $competence]);
                    }
                }
            });
    }
};
