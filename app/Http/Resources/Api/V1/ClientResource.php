<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Client */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'whatsapp' => $this->whatsapp,
            'notes' => $this->notes,
            'main_pains' => $this->main_pains,
            'service_duration_minutes' => $this->service_duration_minutes,
            'client_origin_id' => $this->client_origin_id,
            'campaign_id' => $this->campaign_id,
            'lead_id' => $this->lead_id,
            'initial_consultation_amount' => $this->initial_consultation_amount,
            'is_active' => $this->is_active,
            'client_origin' => ClientOriginResource::make($this->whenLoaded('clientOrigin')),
            'campaign' => CampaignResource::make($this->whenLoaded('campaign')),
            'lead' => LeadResource::make($this->whenLoaded('lead')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
