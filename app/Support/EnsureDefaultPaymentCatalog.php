<?php

namespace App\Support;

use App\Models\CardBrand;
use App\Models\CardOperator;
use App\Models\Clinic;
use App\Models\PaymentMethod;

class EnsureDefaultPaymentCatalog
{
    /**
     * @var list<array{
     *     name: string,
     *     code: string,
     *     kind: string,
     *     requires_card_meta: bool,
     *     fee_percent: string|null,
     *     fee_fixed: string|null
     * }>
     */
    public const METHODS = [
        [
            'name' => 'Dinheiro',
            'code' => 'dinheiro',
            'kind' => PaymentMethod::KIND_CASH,
            'requires_card_meta' => false,
            'fee_percent' => null,
            'fee_fixed' => null,
        ],
        [
            'name' => 'PIX',
            'code' => 'pix',
            'kind' => PaymentMethod::KIND_PIX,
            'requires_card_meta' => false,
            'fee_percent' => null,
            'fee_fixed' => null,
        ],
        [
            'name' => 'Cartão de débito',
            'code' => 'cartao_debito',
            'kind' => PaymentMethod::KIND_DEBIT_CARD,
            'requires_card_meta' => true,
            'fee_percent' => null,
            'fee_fixed' => null,
        ],
        [
            'name' => 'Cartão de crédito',
            'code' => 'cartao_credito',
            'kind' => PaymentMethod::KIND_CREDIT_CARD,
            'requires_card_meta' => true,
            'fee_percent' => null,
            'fee_fixed' => null,
        ],
        [
            'name' => 'Boleto',
            'code' => 'boleto',
            'kind' => PaymentMethod::KIND_BOLETO,
            'requires_card_meta' => false,
            'fee_percent' => null,
            'fee_fixed' => '2.50',
        ],
        [
            'name' => 'Cheque',
            'code' => 'cheque',
            'kind' => PaymentMethod::KIND_CHECK,
            'requires_card_meta' => false,
            'fee_percent' => null,
            'fee_fixed' => null,
        ],
        [
            'name' => 'Outros',
            'code' => 'outros',
            'kind' => PaymentMethod::KIND_OTHER,
            'requires_card_meta' => false,
            'fee_percent' => null,
            'fee_fixed' => null,
        ],
    ];

    /**
     * @var list<array{name: string, code: string}>
     */
    public const BRANDS = [
        ['name' => 'Visa', 'code' => 'visa'],
        ['name' => 'Mastercard', 'code' => 'mastercard'],
        ['name' => 'Elo', 'code' => 'elo'],
        ['name' => 'American Express', 'code' => 'amex'],
        ['name' => 'Hipercard', 'code' => 'hipercard'],
        ['name' => 'Cabal', 'code' => 'cabal'],
        ['name' => 'Diners', 'code' => 'diners'],
    ];

    /**
     * Default Brazilian acquirers / maquininhas (curated market set).
     *
     * @var list<array{name: string, code: string}>
     */
    public const OPERATORS = [
        ['name' => 'Cielo', 'code' => 'cielo'],
        ['name' => 'Rede', 'code' => 'rede'],
        ['name' => 'Getnet', 'code' => 'getnet'],
        ['name' => 'Stone', 'code' => 'stone'],
        ['name' => 'PagBank', 'code' => 'pagbank'],
        ['name' => 'Mercado Pago', 'code' => 'mercado_pago'],
        ['name' => 'SafraPay', 'code' => 'safrapay'],
        ['name' => 'Sipag', 'code' => 'sipag'],
        ['name' => 'SumUp', 'code' => 'sumup'],
        ['name' => 'InfinitePay', 'code' => 'infinitepay'],
        ['name' => 'PicPay', 'code' => 'picpay'],
        ['name' => 'Zoop', 'code' => 'zoop'],
        ['name' => 'Bin', 'code' => 'bin'],
        ['name' => 'Vero', 'code' => 'vero'],
        ['name' => 'Granito', 'code' => 'granito'],
        ['name' => 'Adyen', 'code' => 'adyen'],
        ['name' => 'Pagar.me', 'code' => 'pagarme'],
    ];

    public static function run(Clinic $clinic): void
    {
        $previous = CurrentClinic::id();
        CurrentClinic::setId($clinic->id);

        try {
            foreach (self::METHODS as $method) {
                PaymentMethod::query()->firstOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'code' => $method['code'],
                    ],
                    [
                        'name' => $method['name'],
                        'kind' => $method['kind'],
                        'requires_card_meta' => $method['requires_card_meta'],
                        'fee_percent' => $method['fee_percent'],
                        'fee_fixed' => $method['fee_fixed'],
                        'is_active' => true,
                    ]
                );
            }

            foreach (self::BRANDS as $brand) {
                CardBrand::query()->firstOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'code' => $brand['code'],
                    ],
                    [
                        'name' => $brand['name'],
                        'is_active' => true,
                    ]
                );
            }

            foreach (self::OPERATORS as $operator) {
                $exists = CardOperator::query()
                    ->where('clinic_id', $clinic->id)
                    ->where(function ($query) use ($operator) {
                        $query->where('code', $operator['code'])
                            ->orWhere('name', $operator['name']);
                    })
                    ->exists();

                if ($exists) {
                    continue;
                }

                CardOperator::query()->create([
                    'clinic_id' => $clinic->id,
                    'name' => $operator['name'],
                    'code' => $operator['code'],
                    'auto_anticipate' => false,
                    'is_active' => true,
                ]);
            }
        } finally {
            CurrentClinic::setId($previous);
        }
    }

    public static function runAll(): int
    {
        $count = 0;

        foreach (Clinic::query()->orderBy('id')->cursor() as $clinic) {
            self::run($clinic);
            $count++;
        }

        return $count;
    }
}
