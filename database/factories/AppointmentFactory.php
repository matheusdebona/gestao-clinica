<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Treatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'treatment_id' => Treatment::factory(),
            'client_id' => Client::factory(),
            'professional_user_id' => null,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => Appointment::DEFAULT_DURATION_MINUTES,
            'notes' => null,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
            'treatment_id' => Treatment::factory()->forClinic($clinic),
            'client_id' => Client::factory()->forClinic($clinic),
        ]);
    }
}
