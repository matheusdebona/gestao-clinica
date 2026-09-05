<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AppointmentConsumption */
class AppointmentConsumptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'product_id' => $this->product_id,
            'sale_item_id' => $this->sale_item_id,
            'source' => $this->source,
            'quantity' => $this->quantity,
            'is_complimentary' => $this->is_complimentary,
            'charged_amount' => $this->charged_amount,
            'sale_payment_id' => $this->sale_payment_id,
            'unit_cost' => $this->unit_cost,
            'line_cost' => $this->line_cost,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'sale_payment' => SalePaymentResource::make($this->whenLoaded('salePayment')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
