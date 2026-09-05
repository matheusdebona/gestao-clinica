<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalePayment>
 */
class SalePaymentFactory extends Factory
{
    protected $model = SalePayment::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => '100.00',
            'card_operator_id' => null,
            'card_brand_id' => null,
            'installments' => null,
            'paid_at' => now(),
        ];
    }
}
