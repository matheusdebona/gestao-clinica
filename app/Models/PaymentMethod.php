<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Support\PaymentFeeCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    public const KIND_CASH = 'cash';

    public const KIND_PIX = 'pix';

    public const KIND_CHECK = 'check';

    public const KIND_CREDIT_CARD = 'credit_card';

    public const KIND_DEBIT_CARD = 'debit_card';

    public const KIND_BOLETO = 'boleto';

    public const KIND_OTHER = 'other';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_CASH,
        self::KIND_PIX,
        self::KIND_CHECK,
        self::KIND_CREDIT_CARD,
        self::KIND_DEBIT_CARD,
        self::KIND_BOLETO,
        self::KIND_OTHER,
    ];

    /** @var list<string> */
    public const CARD_KINDS = [
        self::KIND_CREDIT_CARD,
        self::KIND_DEBIT_CARD,
    ];

    /** @use HasFactory<\Database\Factories\PaymentMethodFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'code',
        'kind',
        'requires_card_meta',
        'fee_percent',
        'fee_fixed',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'requires_card_meta' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'requires_card_meta' => 'boolean',
            'fee_percent' => 'decimal:4',
            'fee_fixed' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function isCardKind(): bool
    {
        return in_array($this->kind, self::CARD_KINDS, true);
    }

    public function cardFeeRules(): HasMany
    {
        return $this->hasMany(CardFeeRule::class);
    }

    public function feeAmountFor(string|float $gross): ?string
    {
        if ($this->isCardKind()) {
            return null;
        }

        return PaymentFeeCalculator::feeAmount($gross, $this->fee_percent, $this->fee_fixed);
    }

    public function netAmountFor(string|float $gross): ?string
    {
        if ($this->isCardKind()) {
            return null;
        }

        return PaymentFeeCalculator::netAmount($gross, $this->fee_percent, $this->fee_fixed);
    }
}
