<?php

namespace Database\Factories;

use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('119########'),
            'client_origin_id' => fn (array $attributes) => ClientOrigin::factory()->create(['clinic_id' => $attributes['clinic_id']])->id,
            'lead_status_id' => fn (array $attributes) => LeadStatus::factory()->create(['clinic_id' => $attributes['clinic_id']])->id,
            'notes' => null,
            'created_by_user_id' => null,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => ['clinic_id' => $clinic->id]);
    }
}
