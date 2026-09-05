<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Clinic;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'purpose' => fake()->sentence(),
            'cost' => '10.0000',
            'sale_price' => '100.00',
            'min_sale_price' => '80.00',
            'stock_quantity' => '0.0000',
            'min_stock' => '5.0000',
            'lead_time_days' => 0,
            'is_active' => true,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(function () use ($clinic) {
            return [
                'clinic_id' => $clinic->id,
                'product_type_id' => ProductType::factory()->state(['clinic_id' => $clinic->id]),
                'brand_id' => Brand::factory()->state(['clinic_id' => $clinic->id]),
                'unit_of_measure_id' => UnitOfMeasure::factory()->state(['clinic_id' => $clinic->id]),
            ];
        });
    }
}
