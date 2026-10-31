<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'client_origin_id' => $this->client_origin_id,
            'lead_status_id' => $this->lead_status_id,
            'notes' => $this->notes,
            'created_by_user_id' => $this->created_by_user_id,
            'client_id' => $this->client_id,
            'converted_at' => $this->converted_at,
            'cancelled_at' => $this->cancelled_at,
            'client_origin' => ClientOriginResource::make($this->whenLoaded('clientOrigin')),
            'status' => LeadStatusResource::make($this->whenLoaded('status')),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy === null ? null : [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'client' => $this->whenLoaded('client', fn () => $this->client === null ? null : [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'whatsapp' => $this->client->whatsapp,
            ]),
            'activities' => LeadActivityResource::collection($this->whenLoaded('activities')),
            'comments_count' => $this->whenCounted('comments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
