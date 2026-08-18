<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Protocol extends Model
{
    /** @use HasFactory<\Database\Factories\ProtocolFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'description',
        'total_cost',
        'products_sale_total',
        'suggested_price',
        'suggested_price_is_manual',
        'min_price',
        'min_price_is_manual',
        'special_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_cost' => 'decimal:4',
            'products_sale_total' => 'decimal:2',
            'suggested_price' => 'decimal:2',
            'suggested_price_is_manual' => 'boolean',
            'min_price' => 'decimal:2',
            'min_price_is_manual' => 'boolean',
            'special_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProtocolItem::class);
    }

    public function marginAtSuggested(): string
    {
        return number_format((float) $this->suggested_price - (float) $this->total_cost, 4, '.', '');
    }

    public function marginAtMin(): string
    {
        return number_format((float) $this->min_price - (float) $this->total_cost, 4, '.', '');
    }

    public function marginAtSpecial(): ?string
    {
        if ($this->special_price === null) {
            return null;
        }

        return number_format((float) $this->special_price - (float) $this->total_cost, 4, '.', '');
    }
}
