<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'client_origin_id' => ClientOrigin::factory(),
            'name' => fake()->unique()->words(3, true),
            'is_active' => true,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
            'client_origin_id' => ClientOrigin::factory()->forClinic($clinic),
        ]);
    }

    public function forOrigin(ClientOrigin $origin): static
    {
        return $this->state(fn () => [
            'clinic_id' => $origin->clinic_id,
            'client_origin_id' => $origin->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
