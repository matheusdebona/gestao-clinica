<?php

namespace App\Support;

use App\Models\Clinic;
use Illuminate\Support\Facades\DB;

class EnsureDefaultPatientTiers
{
    /** @var list<array{name: string, color: string, min_amount: string}> */
    public const DEFAULTS = [
        ['name' => 'Bronze', 'color' => 'bronze', 'min_amount' => '0.00'],
        ['name' => 'Prata', 'color' => 'silver', 'min_amount' => '5000.01'],
        ['name' => 'Ouro', 'color' => 'gold', 'min_amount' => '10000.01'],
        ['name' => 'Diamante', 'color' => 'diamond', 'min_amount' => '20000.01'],
    ];

    /**
     * Cria as faixas padrão só para clínica sem nenhuma faixa (criação, migração ou "restaurar sugestão").
     *
     * @return bool true quando criou as faixas
     */
    public static function run(Clinic|int $clinic): bool
    {
        $clinicId = $clinic instanceof Clinic ? $clinic->id : $clinic;

        return DB::transaction(function () use ($clinicId): bool {
            if (DB::table('patient_tiers')->where('clinic_id', $clinicId)->exists()) {
                return false;
            }

            $now = now();
            DB::table('patient_tiers')->insert(array_map(fn (array $row) => [
                ...$row,
                'clinic_id' => $clinicId,
                'created_at' => $now,
                'updated_at' => $now,
            ], self::DEFAULTS));

            return true;
        });
    }

    public static function runAll(): void
    {
        DB::table('clinics')->orderBy('id')->pluck('id')->each(fn ($id) => self::run((int) $id));
    }
}
