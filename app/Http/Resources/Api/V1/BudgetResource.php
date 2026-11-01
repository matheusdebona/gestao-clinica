<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Budget;
use App\Services\BudgetFollowUpService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Budget */
class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'sale_id' => $this->sale_id,
            'client_id' => $this->client_id,
            'created_by_user_id' => $this->created_by_user_id,
            'version' => $this->version,
            'status' => $this->status,
            'expected_amount' => $this->expected_amount,
            'effective_amount' => $this->effective_amount,
            'min_amount' => $this->min_amount,
            'notes' => $this->notes,
            'valid_until' => $this->valid_until,
            'sent_at' => $this->sent_at,
            'accepted_at' => $this->accepted_at,
            'rejected_at' => $this->rejected_at,
            'follow_up_sent_at' => $this->follow_up_sent_at,
            'follow_up_count' => (int) $this->follow_up_count,
            // Orçamento esquecido (enviado e sem resposta além de um prazo) ou null.
            'forgotten' => app(BudgetFollowUpService::class)->info($this->resource),
            'items' => BudgetItemResource::collection($this->whenLoaded('items')),
            'client' => ClientResource::make($this->whenLoaded('client')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
