<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Protocol;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Protocol>
 */
class ProtocolFactory extends Factory
{
    protected $model = Protocol::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'total_cost' => '0.0000',
            'products_sale_total' => '0.00',
            'suggested_price' => '0.00',
            'suggested_price_is_manual' => false,
            'min_price' => '0.00',
            'min_price_is_manual' => false,
            'special_price' => null,
            'is_active' => true,
        ];
    }
}
