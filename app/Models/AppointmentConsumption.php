<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentConsumption extends Model
{
    public const SOURCE_SUGGESTED = 'suggested';

    public const SOURCE_EXTRA = 'extra';

    /** @var list<string> */
    public const SOURCES = [
        self::SOURCE_SUGGESTED,
        self::SOURCE_EXTRA,
    ];

    /** @use HasFactory<\Database\Factories\AppointmentConsumptionFactory> */
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'product_id',
        'sale_item_id',
        'source',
        'quantity',
        'is_complimentary',
        'charged_amount',
        'sale_payment_id',
        'unit_cost',
        'line_cost',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_complimentary' => false,
        'charged_amount' => '0.00',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'is_complimentary' => 'boolean',
            'charged_amount' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'line_cost' => 'decimal:4',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function salePayment(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class);
    }
}
