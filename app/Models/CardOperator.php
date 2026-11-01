<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Database\Factories\CardOperatorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardOperator extends Model
{
    /** @use HasFactory<CardOperatorFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'code',
        'auto_anticipate',
        'anticipation_rate_percent',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'auto_anticipate' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'auto_anticipate' => 'boolean',
            'anticipation_rate_percent' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function cardFeeRules(): HasMany
    {
        return $this->hasMany(CardFeeRule::class);
    }
}
