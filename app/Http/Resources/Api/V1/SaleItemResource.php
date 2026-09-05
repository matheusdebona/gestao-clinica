<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SaleItem */
class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'source_protocol_id' => $this->source_protocol_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'unit_cost' => $this->unit_cost,
            'min_unit_price' => $this->min_unit_price,
            'line_total' => $this->line_total,
            'line_min_total' => number_format((float) $this->min_unit_price * (float) $this->quantity, 2, '.', ''),
            'is_below_minimum' => (float) $this->unit_price < (float) $this->min_unit_price,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
