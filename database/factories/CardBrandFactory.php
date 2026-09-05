<?php

namespace Database\Factories;

use App\Models\CardBrand;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CardBrand>
 */
class CardBrandFactory extends Factory
{
    protected $model = CardBrand::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Visa', 'Mastercard', 'Elo', 'Amex']);

        return [
            'clinic_id' => Clinic::factory(),
            'name' => $name,
            'code' => Str::slug($name, '_'),
            'is_active' => true,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
        ]);
    }
}
