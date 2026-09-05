<?php

namespace App\Http\Requests\Api\V1\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncSalePaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'payments' => ['present', 'array'],
            'payments.*.payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true)
                ),
            ],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.card_operator_id' => [
                'nullable',
                'integer',
                Rule::exists('card_operators', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'payments.*.card_brand_id' => [
                'nullable',
                'integer',
                Rule::exists('card_brands', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'payments.*.installments' => ['nullable', 'integer', 'min:1', 'max:48'],
            'payments.*.paid_at' => ['nullable', 'date'],
        ];
    }
}
