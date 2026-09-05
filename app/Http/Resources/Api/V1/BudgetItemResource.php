<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BudgetItem */
class BudgetItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'source_protocol_id' => $this->source_protocol_id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'list_unit_price' => $this->list_unit_price,
            'list_line_total' => $this->list_line_total,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            'unit_cost' => $this->unit_cost,
            'min_unit_price' => $this->min_unit_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
