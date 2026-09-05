<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Protocol;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Sale */
class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['items.product', 'items.sourceProtocol', 'payments.paymentMethod', 'client', 'soldByUser']);

        $minAmount = $this->minAmount();
        $costTotal = $this->costTotal();
        $paymentsTotal = $this->paymentsTotal();

        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'client_id' => $this->client_id,
            'sold_by_user_id' => $this->sold_by_user_id,
            'sold_at' => $this->sold_at,
            'expected_amount' => $this->expected_amount,
            'effective_amount' => $this->effective_amount,
            'effective_amount_is_manual' => $this->effective_amount_is_manual,
            'min_amount' => $minAmount,
            'cost_total' => $costTotal,
            'margin_at_effective' => $this->marginAtEffective(),
            'is_below_minimum' => $this->isBelowMinimum(),
            'payments_total' => $paymentsTotal,
            'payments_balance' => number_format((float) $this->effective_amount - (float) $paymentsTotal, 2, '.', ''),
            'status' => $this->status,
            'notes' => $this->notes,
            'client' => ClientResource::make($this->whenLoaded('client')),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => SalePaymentResource::collection($this->whenLoaded('payments')),
            'protocol_references' => $this->protocolReferences(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function protocolReferences(): array
    {
        if (! $this->relationLoaded('items')) {
            return [];
        }

        $protocolIds = $this->items
            ->pluck('source_protocol_id')
            ->filter()
            ->unique()
            ->values();

        if ($protocolIds->isEmpty()) {
            return [];
        }

        $loaded = $this->items
            ->map(fn (SaleItem $item) => $item->relationLoaded('sourceProtocol') ? $item->sourceProtocol : null)
            ->filter()
            ->unique('id')
            ->keyBy('id');

        $missingIds = $protocolIds->reject(fn ($id) => $loaded->has($id));
        if ($missingIds->isNotEmpty()) {
            Protocol::query()->whereIn('id', $missingIds)->get()->each(
                fn (Protocol $protocol) => $loaded->put($protocol->id, $protocol)
            );
        }

        return $protocolIds->map(function ($id) use ($loaded) {
            /** @var Protocol|null $protocol */
            $protocol = $loaded->get($id);
            if ($protocol === null) {
                return null;
            }

            return [
                'id' => $protocol->id,
                'name' => $protocol->name,
                'suggested_price' => $protocol->suggested_price,
                'min_price' => $protocol->min_price,
                'special_price' => $protocol->special_price,
            ];
        })->filter()->values()->all();
    }
}
