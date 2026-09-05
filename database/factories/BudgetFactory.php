<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'sale_id' => Sale::factory(),
            'client_id' => Client::factory(),
            'created_by_user_id' => User::factory(),
            'version' => 1,
            'status' => Budget::STATUS_DRAFT,
            'expected_amount' => '100.00',
            'effective_amount' => '100.00',
            'min_amount' => '80.00',
            'notes' => null,
            'valid_until' => null,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
            'sale_id' => Sale::factory()->forClinic($clinic),
            'client_id' => Client::factory()->forClinic($clinic),
            'created_by_user_id' => User::factory()->forClinic($clinic),
        ]);
    }
}
