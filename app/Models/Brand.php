<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function productTypes(): HasMany
    {
        return $this->hasMany(ProductType::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
