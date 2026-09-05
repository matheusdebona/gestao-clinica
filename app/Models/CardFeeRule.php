<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Support\PaymentFeeCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardFeeRule extends Model
{
    /** @use HasFactory<\Database\Factories\CardFeeRuleFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'payment_method_id',
        'card_operator_id',
        'card_brand_id',
        'installments',
        'fee_percent',
        'fee_fixed',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'installments' => 'integer',
            'fee_percent' => 'decimal:4',
            'fee_fixed' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cardOperator(): BelongsTo
    {
        return $this->belongsTo(CardOperator::class);
    }

    public function cardBrand(): BelongsTo
    {
        return $this->belongsTo(CardBrand::class);
    }

    public function feeAmountFor(string|float $gross): string
    {
        return PaymentFeeCalculator::feeAmount($gross, $this->fee_percent, $this->fee_fixed);
    }

    public function netAmountFor(string|float $gross): string
    {
        return PaymentFeeCalculator::netAmount($gross, $this->fee_percent, $this->fee_fixed);
    }
}
