<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentMethod */
class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'code' => $this->code,
            'kind' => $this->kind,
            'requires_card_meta' => $this->requires_card_meta,
            'fee_percent' => $this->when($this->canSeeFees($request), fn () => $this->fee_percent),
            'fee_fixed' => $this->when($this->canSeeFees($request), fn () => $this->fee_fixed),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function canSeeFees(Request $request): bool
    {
        return (bool) $request->user()?->can('finance.view');
    }
}
