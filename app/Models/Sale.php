<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
    ];

    /** @use HasFactory<\Database\Factories\SaleFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'client_id',
        'sold_by_user_id',
        'sold_at',
        'expected_amount',
        'effective_amount',
        'effective_amount_is_manual',
        'status',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'expected_amount' => '0.00',
        'effective_amount' => '0.00',
        'effective_amount_is_manual' => false,
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'expected_amount' => 'decimal:2',
            'effective_amount' => 'decimal:2',
            'effective_amount_is_manual' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function soldByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function minAmount(): string
    {
        $total = $this->items->sum(
            fn (SaleItem $item) => (float) $item->min_unit_price * (float) $item->quantity
        );

        return number_format($total, 2, '.', '');
    }

    public function costTotal(): string
    {
        $total = $this->items->sum(
            fn (SaleItem $item) => (float) $item->unit_cost * (float) $item->quantity
        );

        return number_format($total, 4, '.', '');
    }

    public function paymentsTotal(): string
    {
        $total = $this->payments->sum(fn (SalePayment $payment) => (float) $payment->amount);

        return number_format($total, 2, '.', '');
    }

    public function isBelowMinimum(): bool
    {
        return (float) $this->effective_amount < (float) $this->minAmount();
    }

    public function marginAtEffective(): string
    {
        return number_format((float) $this->effective_amount - (float) $this->costTotal(), 4, '.', '');
    }
}
