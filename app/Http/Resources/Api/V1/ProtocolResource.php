<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Protocol */
class ProtocolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'description' => $this->description,
            'total_cost' => $this->total_cost,
            'products_sale_total' => $this->products_sale_total,
            'suggested_price' => $this->suggested_price,
            'suggested_price_is_manual' => $this->suggested_price_is_manual,
            'min_price' => $this->min_price,
            'min_price_is_manual' => $this->min_price_is_manual,
            'special_price' => $this->special_price,
            'margin_at_suggested' => $this->marginAtSuggested(),
            'margin_at_min' => $this->marginAtMin(),
            'margin_at_special' => $this->marginAtSpecial(),
            'is_active' => $this->is_active,
            'items' => ProtocolItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
