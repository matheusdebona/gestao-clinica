<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'product_type_id',
        'brand_id',
        'unit_of_measure_id',
        'name',
        'sku',
        'purpose',
        'cost',
        'sale_price',
        'min_sale_price',
        'stock_quantity',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:4',
            'sale_price' => 'decimal:2',
            'min_sale_price' => 'decimal:2',
            'stock_quantity' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function unitMargin(): ?string
    {
        return number_format((float) $this->sale_price - (float) $this->cost, 4, '.', '');
    }

    public function unitMarginPercent(): ?string
    {
        $sale = (float) $this->sale_price;
        if ($sale <= 0) {
            return null;
        }

        return number_format((((float) $this->sale_price - (float) $this->cost) / $sale) * 100, 2, '.', '');
    }

    public function inventoryValue(): string
    {
        return number_format((float) $this->stock_quantity * (float) $this->cost, 4, '.', '');
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_quantity <= (float) $this->min_stock;
    }
}
