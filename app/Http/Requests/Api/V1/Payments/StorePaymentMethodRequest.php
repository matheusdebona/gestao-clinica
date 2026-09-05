<?php

namespace App\Http\Requests\Api\V1\Payments;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $kind = $this->input('kind');

        if (in_array($kind, PaymentMethod::CARD_KINDS, true)) {
            $this->merge([
                'requires_card_meta' => true,
                'fee_percent' => null,
                'fee_fixed' => null,
            ]);
        } elseif ($this->has('kind')) {
            $this->merge([
                'requires_card_meta' => false,
            ]);
        }
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash:ascii',
                Rule::unique('payment_methods', 'code')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'kind' => ['required', 'string', Rule::in(PaymentMethod::KINDS)],
            'requires_card_meta' => ['sometimes', 'boolean'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
