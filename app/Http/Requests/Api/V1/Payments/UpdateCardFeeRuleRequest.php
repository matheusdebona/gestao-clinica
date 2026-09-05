<?php

namespace App\Http\Requests\Api\V1\Payments;

use App\Models\CardFeeRule;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCardFeeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'payment_method_id' => [
                'sometimes',
                'integer',
                Rule::exists('payment_methods', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'card_operator_id' => [
                'sometimes',
                'integer',
                Rule::exists('card_operators', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'card_brand_id' => [
                'sometimes',
                'integer',
                Rule::exists('card_brands', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'installments' => ['sometimes', 'integer', 'min:1', 'max:48'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CardFeeRule $rule */
            $rule = $this->route('card_fee_rule');

            $feePercent = $this->exists('fee_percent') ? $this->input('fee_percent') : $rule->fee_percent;
            $feeFixed = $this->exists('fee_fixed') ? $this->input('fee_fixed') : $rule->fee_fixed;

            if ($feePercent === null && $feeFixed === null) {
                $validator->errors()->add('fee_percent', 'Informe fee_percent e/ou fee_fixed.');
            }

            $methodId = $this->input('payment_method_id', $rule->payment_method_id);
            $method = PaymentMethod::query()->find($methodId);
            if ($method === null) {
                return;
            }

            if (! $method->isCardKind()) {
                $validator->errors()->add('payment_method_id', 'A regra de taxa exige método de cartão.');
            }

            $installments = (int) $this->input('installments', $rule->installments);
            if ($method->kind === PaymentMethod::KIND_DEBIT_CARD && $installments !== 1) {
                $validator->errors()->add('installments', 'Débito deve usar 1 parcela.');
            }

            $exists = CardFeeRule::query()
                ->where('clinic_id', $this->user()?->clinic_id)
                ->where('payment_method_id', $methodId)
                ->where('card_operator_id', $this->input('card_operator_id', $rule->card_operator_id))
                ->where('card_brand_id', $this->input('card_brand_id', $rule->card_brand_id))
                ->where('installments', $installments)
                ->where('id', '!=', $rule->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('installments', 'Já existe regra para esta combinação.');
            }
        });
    }
}
