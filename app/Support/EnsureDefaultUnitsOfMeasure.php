<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\UnitOfMeasure;

class EnsureDefaultUnitsOfMeasure
{
    /**
     * @var list<array{name: string, symbol: string}>
     */
    public const UNITS = [
        ['symbol' => 'un', 'name' => 'Unidade'],
        ['symbol' => 'ml', 'name' => 'Mililitro'],
        ['symbol' => 'mg', 'name' => 'Miligrama'],
        ['symbol' => 'g', 'name' => 'Grama'],
        ['symbol' => 'kg', 'name' => 'Quilograma'],
        ['symbol' => 'cx', 'name' => 'Caixa'],
        ['symbol' => 'frasco', 'name' => 'Frasco'],
        ['symbol' => 'seringa', 'name' => 'Seringa'],
        ['symbol' => 'ampola', 'name' => 'Ampola'],
        ['symbol' => 'par', 'name' => 'Par'],
    ];

    public static function run(Clinic $clinic): void
    {
        $previous = CurrentClinic::id();
        CurrentClinic::setId($clinic->id);

        try {
            foreach (self::UNITS as $unit) {
                UnitOfMeasure::query()->firstOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'symbol' => $unit['symbol'],
                    ],
                    [
                        'name' => $unit['name'],
                        'is_active' => true,
                    ]
                );
            }
        } finally {
            CurrentClinic::setId($previous);
        }
    }

    public static function runAll(): int
    {
        $count = 0;

        foreach (Clinic::query()->orderBy('id')->cursor() as $clinic) {
            self::run($clinic);
            $count++;
        }

        return $count;
    }
}
