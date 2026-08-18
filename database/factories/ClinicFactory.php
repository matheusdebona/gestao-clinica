<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    protected $model = Clinic::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Clínica',
            'document' => fake()->numerify('##############'),
            'phone' => fake()->numerify('119########'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'settings' => [
                'locale' => 'pt_BR',
                'currency' => 'BRL',
            ],
            'is_active' => true,
        ];
    }
}
