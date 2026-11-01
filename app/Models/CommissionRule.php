<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use BelongsToClinic;

    public const ROLE_SELLER = 'seller';

    public const ROLE_PROFESSIONAL = 'professional';

    public const ROLES = [self::ROLE_SELLER, self::ROLE_PROFESSIONAL];

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const TYPES = [self::TYPE_PERCENT, self::TYPE_FIXED];

    /** Valor da venda/sessão. */
    public const BASE_GROSS = 'gross';

    /** Valor após taxas de cartão, antecipação e impostos. */
    public const BASE_NET = 'net';

    public const BASES = [self::BASE_GROSS, self::BASE_NET];

    protected $fillable = [
        'clinic_id',
        'role',
        'user_id',
        'protocol_id',
        'calc_type',
        'value',
        'base',
        'is_active',
        'notes',
    ];

    protected $attributes = [
        'base' => self::BASE_GROSS,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    /** Quanto mais específica, maior (pessoa + protocolo > pessoa > protocolo > geral). */
    public function specificity(): int
    {
        return ($this->user_id !== null ? 2 : 0) + ($this->protocol_id !== null ? 1 : 0);
    }
}
