<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SalePayment */
class SalePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_method_id' => $this->payment_method_id,
            'amount' => $this->amount,
            'card_operator_id' => $this->card_operator_id,
            'card_brand_id' => $this->card_brand_id,
            'installments' => $this->installments,
            'paid_at' => $this->paid_at,
            'payment_method' => PaymentMethodResource::make($this->whenLoaded('paymentMethod')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
