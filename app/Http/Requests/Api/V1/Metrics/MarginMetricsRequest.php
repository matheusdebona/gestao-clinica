<?php

namespace App\Http\Requests\Api\V1\Metrics;

use App\Services\MarginMetricsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarginMetricsRequest extends FormRequest
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
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'mode' => ['sometimes', 'nullable', Rule::in(MarginMetricsService::MODES)],
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

            if ($from === null || $to === null) {
                return;
            }

            if ($from->diffInDays($to) > 1825) {
                $validator->errors()->add('to', 'The selected period may not exceed 1825 days (~5 years).');
            }
        });
    }
}
