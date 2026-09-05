<?php

namespace Database\Factories;

use App\Models\CardOperator;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CardOperator>
 */
class CardOperatorFactory extends Factory
{
    protected $model = CardOperator::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'clinic_id' => Clinic::factory(),
            'name' => $name,
            'code' => Str::slug($name, '_'),
            'auto_anticipate' => false,
            'is_active' => true,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
        ]);
    }

    public function autoAnticipate(): static
    {
        return $this->state(fn () => [
            'auto_anticipate' => true,
        ]);
    }
}
