<?php

namespace App\Http\Resources\Api\V1;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockMovement */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'cost_before' => $this->cost_before,
            'cost_after' => $this->cost_after,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'reason' => $this->reason,
            'loss_reason' => $this->loss_reason,
            'loss_value' => $this->when((bool) $request->user()?->can('finance.view'), fn () => $this->loss_value),
            'notes' => $this->notes,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
        ];
    }
}
