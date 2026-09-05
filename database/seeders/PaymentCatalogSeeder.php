<?php

namespace Database\Seeders;

use App\Models\CardBrand;
use App\Models\Clinic;
use App\Models\PaymentMethod;
use App\Support\CurrentClinic;
use Illuminate\Database\Seeder;

class PaymentCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::query()->first();
        if ($clinic === null) {
            return;
        }

        CurrentClinic::setId($clinic->id);

        $methods = [
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
                'name' => 'Cheque',
                'code' => 'cheque',
                'kind' => PaymentMethod::KIND_CHECK,
                'requires_card_meta' => false,
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
                'name' => 'Cartão de débito',
                'code' => 'cartao_debito',
                'kind' => PaymentMethod::KIND_DEBIT_CARD,
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
                'name' => 'Outros',
                'code' => 'outros',
                'kind' => PaymentMethod::KIND_OTHER,
                'requires_card_meta' => false,
                'fee_percent' => null,
                'fee_fixed' => null,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->firstOrCreate(
                ['clinic_id' => $clinic->id, 'code' => $method['code']],
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

        $brands = [
            ['name' => 'Visa', 'code' => 'visa'],
            ['name' => 'Mastercard', 'code' => 'mastercard'],
            ['name' => 'Elo', 'code' => 'elo'],
            ['name' => 'American Express', 'code' => 'amex'],
            ['name' => 'Hipercard', 'code' => 'hipercard'],
            ['name' => 'Cabal', 'code' => 'cabal'],
        ];

        foreach ($brands as $brand) {
            CardBrand::query()->firstOrCreate(
                ['clinic_id' => $clinic->id, 'code' => $brand['code']],
                ['name' => $brand['name'], 'is_active' => true]
            );
        }

        CurrentClinic::forget();
    }
}
