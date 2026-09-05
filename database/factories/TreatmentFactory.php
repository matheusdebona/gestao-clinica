<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Clinic;
use App\Models\Sale;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Treatment>
 */
class TreatmentFactory extends Factory
{
    protected $model = Treatment::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'sale_id' => Sale::factory(),
            'client_id' => Client::factory(),
            'opened_by_user_id' => User::factory(),
            'status' => Treatment::STATUS_OPEN,
            'total_cost' => '0.0000',
            'notes' => null,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
            'sale_id' => Sale::factory()->forClinic($clinic),
            'client_id' => Client::factory()->forClinic($clinic),
            'opened_by_user_id' => User::factory()->forClinic($clinic),
        ]);
    }
}
