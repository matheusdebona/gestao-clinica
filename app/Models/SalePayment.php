<?php

namespace App\Models;

use Database\Factories\SalePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    /** @use HasFactory<SalePaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'payment_method_id',
        'amount',
        'card_operator_id',
        'card_brand_id',
        'installments',
        'paid_at',
        'fee_source',
        'fee_percent_applied',
        'fee_fixed_applied',
        'fee_amount',
        'anticipation_percent_applied',
        'anticipation_fee_amount',
        'net_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'installments' => 'integer',
            'paid_at' => 'datetime',
            'fee_percent_applied' => 'decimal:4',
            'fee_fixed_applied' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'anticipation_percent_applied' => 'decimal:4',
            'anticipation_fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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
}
