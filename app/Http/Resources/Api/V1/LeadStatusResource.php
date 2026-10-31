<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LeadStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeadStatus */
class LeadStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'system_key' => $this->system_key,
            'leads_count' => $this->whenCounted('leads'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
