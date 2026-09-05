<?php

namespace App\Http\Requests\Api\V1\Payments;

use App\Models\CardFeeRule;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCardFeeRuleRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'card_operator_id' => [
                'required',
                'integer',
                Rule::exists('card_operators', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'card_brand_id' => [
                'required',
                'integer',
                Rule::exists('card_brands', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'installments' => ['required', 'integer', 'min:1', 'max:48'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('fee_percent') === null && $this->input('fee_fixed') === null) {
                $validator->errors()->add('fee_percent', 'Informe fee_percent e/ou fee_fixed.');
            }

            $methodId = $this->input('payment_method_id');
            if ($methodId === null) {
                return;
            }

            $method = PaymentMethod::query()->find($methodId);
            if ($method === null) {
                return;
            }

            if (! $method->isCardKind()) {
                $validator->errors()->add('payment_method_id', 'A regra de taxa exige método de cartão.');
            }

            if ($method->kind === PaymentMethod::KIND_DEBIT_CARD && (int) $this->input('installments') !== 1) {
                $validator->errors()->add('installments', 'Débito deve usar 1 parcela.');
            }

            $exists = CardFeeRule::query()
                ->where('clinic_id', $this->user()?->clinic_id)
                ->where('payment_method_id', $this->input('payment_method_id'))
                ->where('card_operator_id', $this->input('card_operator_id'))
                ->where('card_brand_id', $this->input('card_brand_id'))
                ->where('installments', $this->input('installments'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('installments', 'Já existe regra para esta combinação.');
            }
        });
    }
}
