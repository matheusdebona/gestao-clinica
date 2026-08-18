<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtocolItem extends Model
{
    /** @use HasFactory<\Database\Factories\ProtocolItemFactory> */
    use HasFactory;

    protected $fillable = [
        'protocol_id',
        'product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lineCost(): string
    {
        return number_format((float) $this->product->cost * (float) $this->quantity, 4, '.', '');
    }

    public function lineSale(): string
    {
        return number_format((float) $this->product->sale_price * (float) $this->quantity, 2, '.', '');
    }
}
