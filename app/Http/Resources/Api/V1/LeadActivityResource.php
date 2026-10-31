<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LeadActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeadActivity */
class LeadActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'note' => $this->note,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'from_status' => LeadStatusResource::make($this->whenLoaded('fromStatus')),
            'to_status' => LeadStatusResource::make($this->whenLoaded('toStatus')),
            'created_at' => $this->created_at,
        ];
    }
}
