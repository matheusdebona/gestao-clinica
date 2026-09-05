<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->name(),
            'whatsapp' => fake()->numerify('119########'),
            'notes' => fake()->optional()->sentence(),
            'main_pains' => fake()->optional()->sentence(),
            'service_duration_minutes' => fake()->optional()->numberBetween(15, 120),
            'is_active' => true,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
