<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductType>
 */
class ProductTypeFactory extends Factory
{
    protected $model = ProductType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'clinic_id' => Clinic::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'is_active' => true,
        ];
    }
}
