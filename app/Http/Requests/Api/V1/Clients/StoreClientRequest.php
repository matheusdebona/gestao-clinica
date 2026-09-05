<?php

namespace App\Http\Requests\Api\V1\Clients;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'main_pains' => ['nullable', 'string'],
            'service_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'client_origin_id' => [
                'nullable',
                'integer',
                Rule::exists('client_origins', 'id')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true)
                ),
            ],
            'campaign_id' => [
                'nullable',
                'integer',
                Rule::exists('campaigns', 'id')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true)
                ),
            ],
            'initial_consultation_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $originId = $this->input('client_origin_id');
            $campaignId = $this->input('campaign_id');

            if ($campaignId === null) {
                return;
            }

            $campaign = Campaign::query()->find($campaignId);
            if ($campaign === null) {
                return;
            }

            if ($originId === null) {
                $validator->errors()->add(
                    'client_origin_id',
                    'client_origin_id is required when campaign_id is provided.'
                );

                return;
            }

            if ((int) $campaign->client_origin_id !== (int) $originId) {
                $validator->errors()->add(
                    'campaign_id',
                    'The selected campaign does not belong to the selected client origin.'
                );
            }
        });
    }
}
