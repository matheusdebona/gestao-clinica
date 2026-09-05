<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetItem extends Model
{
    /** @use HasFactory<\Database\Factories\BudgetItemFactory> */
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'product_id',
        'source_protocol_id',
        'product_name',
        'quantity',
        'list_unit_price',
        'list_line_total',
        'unit_price',
        'line_total',
        'unit_cost',
        'min_unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'list_unit_price' => 'decimal:2',
            'list_line_total' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'min_unit_price' => 'decimal:2',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceProtocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class, 'source_protocol_id');
    }
}
