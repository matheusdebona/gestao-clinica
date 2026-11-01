<?php

namespace App\Http\Requests\Api\V1\Metrics;

use App\Support\CurrentClinic;
use Illuminate\Validation\Rule;

class SalesFunnelMetricsRequest extends LeadMetricsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $clinicId = CurrentClinic::id() ?? $this->user()?->clinic_id;

        return parent::rules() + [
            'client_origin_id' => ['sometimes', 'nullable', 'integer', Rule::exists('client_origins', 'id')->where('clinic_id', $clinicId)],
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('clinic_id', $clinicId)],
        ];
    }
}
