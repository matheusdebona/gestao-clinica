<?php

namespace Database\Factories;

use App\Models\CardBrand;
use App\Models\CardFeeRule;
use App\Models\CardOperator;
use App\Models\Clinic;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardFeeRule>
 */
class CardFeeRuleFactory extends Factory
{
    protected $model = CardFeeRule::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'payment_method_id' => PaymentMethod::factory()->creditCard(),
            'card_operator_id' => CardOperator::factory(),
            'card_brand_id' => CardBrand::factory(),
            'installments' => 1,
            'fee_percent' => '3.5000',
            'fee_fixed' => null,
            'is_active' => true,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(function () use ($clinic) {
            return [
                'clinic_id' => $clinic->id,
                'payment_method_id' => PaymentMethod::factory()->forClinic($clinic)->creditCard(),
                'card_operator_id' => CardOperator::factory()->forClinic($clinic),
                'card_brand_id' => CardBrand::factory()->forClinic($clinic),
            ];
        });
    }
}
