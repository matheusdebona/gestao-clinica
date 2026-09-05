<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Clinic;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'client_id' => Client::factory(),
            'sold_by_user_id' => User::factory(),
            'sold_at' => now(),
            'expected_amount' => '0.00',
            'effective_amount' => '0.00',
            'effective_amount_is_manual' => false,
            'status' => Sale::STATUS_DRAFT,
            'notes' => null,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
            'client_id' => Client::factory()->forClinic($clinic),
            'sold_by_user_id' => User::factory()->forClinic($clinic),
        ]);
    }
}
