<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadStatus>
 */
class LeadStatusFactory extends Factory
{
    protected $model = LeadStatus::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => 'Status '.fake()->unique()->numerify('###'),
            'color' => 'muted',
            'sort_order' => 100,
            'is_active' => true,
            'is_default' => false,
            'system_key' => null,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => ['clinic_id' => $clinic->id]);
    }
}
