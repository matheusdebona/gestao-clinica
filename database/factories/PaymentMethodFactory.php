<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'clinic_id' => Clinic::factory(),
            'name' => Str::title($name),
            'code' => Str::slug($name, '_'),
            'kind' => PaymentMethod::KIND_PIX,
            'requires_card_meta' => false,
            'fee_percent' => null,
            'fee_fixed' => null,
            'is_active' => true,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
        ]);
    }

    public function creditCard(): static
    {
        return $this->state(fn () => [
            'name' => 'Cartão de crédito',
            'code' => 'cartao_credito',
            'kind' => PaymentMethod::KIND_CREDIT_CARD,
            'requires_card_meta' => true,
            'fee_percent' => null,
            'fee_fixed' => null,
        ]);
    }

    public function debitCard(): static
    {
        return $this->state(fn () => [
            'name' => 'Cartão de débito',
            'code' => 'cartao_debito',
            'kind' => PaymentMethod::KIND_DEBIT_CARD,
            'requires_card_meta' => true,
            'fee_percent' => null,
            'fee_fixed' => null,
        ]);
    }
}
