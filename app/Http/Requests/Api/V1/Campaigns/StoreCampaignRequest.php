<?php

namespace App\Http\Requests\Api\V1\Campaigns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $originId = $this->input('client_origin_id');

        return [
            'client_origin_id' => [
                'required',
                'integer',
                Rule::exists('client_origins', 'id')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true)
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('campaigns', 'name')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('client_origin_id', $originId)
                ),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
