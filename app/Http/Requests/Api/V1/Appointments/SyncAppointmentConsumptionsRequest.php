<?php

namespace App\Http\Requests\Api\V1\Appointments;

use App\Models\AppointmentConsumption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncAppointmentConsumptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'consumptions' => ['present', 'array'],
            'consumptions.*.source' => ['required', 'string', Rule::in(AppointmentConsumption::SOURCES)],
            'consumptions.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'consumptions.*.quantity' => ['required', 'numeric', 'gt:0'],
            'consumptions.*.sale_item_id' => [
                'nullable',
                'integer',
                'required_if:consumptions.*.source,'.AppointmentConsumption::SOURCE_SUGGESTED,
                Rule::exists('sale_items', 'id'),
            ],
            'consumptions.*.is_complimentary' => ['sometimes', 'boolean'],
            'consumptions.*.charged_amount' => ['sometimes', 'numeric', 'min:0'],
            'consumptions.*.payment' => ['sometimes', 'nullable', 'array'],
            'consumptions.*.payment.payment_method_id' => [
                'required_with:consumptions.*.payment',
                'integer',
                Rule::exists('payment_methods', 'id')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true)
                ),
            ],
            'consumptions.*.payment.card_operator_id' => [
                'nullable',
                'integer',
                Rule::exists('card_operators', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'consumptions.*.payment.card_brand_id' => [
                'nullable',
                'integer',
                Rule::exists('card_brands', 'id')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'consumptions.*.payment.installments' => ['nullable', 'integer', 'min:1', 'max:48'],
            'consumptions.*.payment.paid_at' => ['nullable', 'date'],
        ];
    }
}
