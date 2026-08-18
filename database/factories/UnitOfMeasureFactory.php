<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->randomElement(['Mililitro', 'Unidade', 'Miligrama']),
            'symbol' => fake()->unique()->lexify('??'),
            'is_active' => true,
        ];
    }
}
