<?php

namespace App\Http\Requests\Api\V1\Metrics;

use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductSalesMetricsRequest extends FormRequest
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
        $clinicId = CurrentClinic::id();

        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'product_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_types', 'id')->where('clinic_id', $clinicId)],
            'product_id' => ['sometimes', 'nullable', 'integer', Rule::exists('products', 'id')->where('clinic_id', $clinicId)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $from = $this->date('from');
            $to = $this->date('to');
            if ($from !== null && $to !== null && $from->diffInDays($to) > 1825) {
                $validator->errors()->add('to', 'The selected period may not exceed 1825 days (~5 years).');
            }
        });
    }
}
