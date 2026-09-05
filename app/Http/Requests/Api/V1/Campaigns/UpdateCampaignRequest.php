<?php

namespace App\Http\Requests\Api\V1\Campaigns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        /** @var \App\Models\Campaign|null $campaign */
        $campaign = $this->route('campaign');
        $originId = $this->input('client_origin_id', $campaign?->client_origin_id);

        return [
            'client_origin_id' => [
                'sometimes',
                'integer',
                Rule::exists('client_origins', 'id')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true)
                ),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('campaigns', 'name')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId)->where('client_origin_id', $originId))
                    ->ignore($campaign?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
