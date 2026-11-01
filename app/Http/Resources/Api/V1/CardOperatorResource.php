<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CardOperator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CardOperator */
class CardOperatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'code' => $this->code,
            'auto_anticipate' => $this->auto_anticipate,
            'anticipation_rate_percent' => $this->when(
                (bool) $request->user()?->can('finance.view'),
                fn () => $this->anticipation_rate_percent,
            ),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
