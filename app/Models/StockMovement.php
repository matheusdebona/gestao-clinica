<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToClinic;

    public const LOSS_EXPIRED = 'expired';

    public const LOSS_DAMAGED = 'damaged';

    public const LOSS_OTHER = 'other';

    /** Saídas valorizadas como perda (custo). Saída sem motivo = ajuste/correção. */
    public const LOSS_REASONS = [self::LOSS_EXPIRED, self::LOSS_DAMAGED, self::LOSS_OTHER];

    protected $fillable = [
        'clinic_id',
        'product_id',
        'user_id',
        'type',
        'quantity',
        'unit_cost',
        'cost_before',
        'cost_after',
        'stock_before',
        'stock_after',
        'reason',
        'loss_reason',
        'loss_value',
        'notes',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'cost_before' => 'decimal:4',
            'cost_after' => 'decimal:4',
            'stock_before' => 'decimal:4',
            'stock_after' => 'decimal:4',
            'loss_value' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
