<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Treatment */
class TreatmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'sale_id' => $this->sale_id,
            'client_id' => $this->client_id,
            'opened_by_user_id' => $this->opened_by_user_id,
            'status' => $this->status,
            'total_cost' => $this->total_cost,
            'notes' => $this->notes,
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),
            'client' => ClientResource::make($this->whenLoaded('client')),
            'sale' => SaleResource::make($this->whenLoaded('sale')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
