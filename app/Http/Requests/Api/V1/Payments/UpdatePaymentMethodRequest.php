<?php

namespace App\Http\Requests\Api\V1\Payments;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $kind = $this->input('kind', $this->route('payment_method')?->kind);

        if (in_array($kind, PaymentMethod::CARD_KINDS, true) && ($this->has('kind') || $this->has('fee_percent') || $this->has('fee_fixed') || $this->has('requires_card_meta'))) {
            $this->merge([
                'requires_card_meta' => true,
                'fee_percent' => null,
                'fee_fixed' => null,
            ]);
        }
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = $this->route('payment_method');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                'alpha_dash:ascii',
                Rule::unique('payment_methods', 'code')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId))
                    ->ignore($paymentMethod->id),
            ],
            'kind' => ['sometimes', 'string', Rule::in(PaymentMethod::KINDS)],
            'requires_card_meta' => ['sometimes', 'boolean'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
