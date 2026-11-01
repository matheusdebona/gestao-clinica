<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Client */
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
            // Faixa pelo total comprado em 12 meses (só na lista e no detalhe do paciente).
            'tier' => $this->when($this->relationLoaded('patientTier'), fn () => $this->patientTier === null ? null : [
                'id' => $this->patientTier->id,
                'name' => $this->patientTier->name,
                'color' => $this->patientTier->color,
            ]),
            'purchases_12m' => $this->when(
                $this->relationLoaded('patientTier') && (bool) $request->user()?->can('sales.view'),
                fn () => number_format((float) $this->getAttribute('purchases_12m'), 2, '.', ''),
            ),
            'client_origin' => ClientOriginResource::make($this->whenLoaded('clientOrigin')),
            'campaign' => CampaignResource::make($this->whenLoaded('campaign')),
            'lead' => LeadResource::make($this->whenLoaded('lead')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
