<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalePayment */
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
            $this->mergeWhen((bool) $request->user()?->can('finance.view'), fn () => [
                'fee_source' => $this->fee_source,
                'fee_percent_applied' => $this->fee_percent_applied,
                'fee_fixed_applied' => $this->fee_fixed_applied,
                'fee_amount' => $this->fee_amount,
                'anticipation_percent_applied' => $this->anticipation_percent_applied,
                'anticipation_fee_amount' => $this->anticipation_fee_amount,
                'net_amount' => $this->net_amount,
            ]),
            'payment_method' => PaymentMethodResource::make($this->whenLoaded('paymentMethod')),
            'card_operator' => CardOperatorResource::make($this->whenLoaded('cardOperator')),
            'card_brand' => CardBrandResource::make($this->whenLoaded('cardBrand')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
