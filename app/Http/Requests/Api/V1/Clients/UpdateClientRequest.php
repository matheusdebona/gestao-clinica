<?php

namespace App\Http\Requests\Api\V1\Clients;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'whatsapp' => ['sometimes', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'main_pains' => ['nullable', 'string'],
            'service_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'client_origin_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('client_origins', 'id')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true)
                ),
            ],
            'campaign_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('campaigns', 'id')->where(
                    fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true)
                ),
            ],
            'initial_consultation_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var \App\Models\Client|null $client */
            $client = $this->route('client');
            $originId = $this->exists('client_origin_id')
                ? $this->input('client_origin_id')
                : $client?->client_origin_id;
            $campaignId = $this->exists('campaign_id')
                ? $this->input('campaign_id')
                : $client?->campaign_id;

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
