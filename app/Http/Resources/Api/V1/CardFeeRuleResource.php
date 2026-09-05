<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CardFeeRule */
class CardFeeRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'payment_method_id' => $this->payment_method_id,
            'card_operator_id' => $this->card_operator_id,
            'card_brand_id' => $this->card_brand_id,
            'installments' => $this->installments,
            'fee_percent' => $this->fee_percent,
            'fee_fixed' => $this->fee_fixed,
            'is_active' => $this->is_active,
            'payment_method' => PaymentMethodResource::make($this->whenLoaded('paymentMethod')),
            'card_operator' => CardOperatorResource::make($this->whenLoaded('cardOperator')),
            'card_brand' => CardBrandResource::make($this->whenLoaded('cardBrand')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
