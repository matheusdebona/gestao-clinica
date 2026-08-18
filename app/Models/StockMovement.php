<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToClinic;

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
