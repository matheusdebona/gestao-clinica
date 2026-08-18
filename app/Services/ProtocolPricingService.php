<?php

namespace App\Services;

use App\Models\Protocol;
use App\Models\ProtocolItem;
use Illuminate\Support\Facades\DB;

class ProtocolPricingService
{
    public function recalculate(Protocol $protocol, bool $forceSuggested = false, bool $forceMin = false): Protocol
    {
        return DB::transaction(function () use ($protocol, $forceSuggested, $forceMin) {
            /** @var Protocol $locked */
            $locked = Protocol::query()->whereKey($protocol->id)->lockForUpdate()->firstOrFail();

            $items = ProtocolItem::query()
                ->with('product')
                ->where('protocol_id', $locked->id)
                ->get();

            $totalCost = 0.0;
            $productsSaleTotal = 0.0;
            $minFromProducts = 0.0;

            foreach ($items as $item) {
                $product = $item->product;
                $qty = (float) $item->quantity;

                $totalCost += (float) $product->cost * $qty;
                $productsSaleTotal += (float) $product->sale_price * $qty;

                $floor = $product->min_sale_price !== null
                    ? (float) $product->min_sale_price
                    : (float) $product->cost;
                $minFromProducts += $floor * $qty;
            }

            $locked->total_cost = $this->money4($totalCost);
            $locked->products_sale_total = $this->money2($productsSaleTotal);

            if ($forceSuggested || ! $locked->suggested_price_is_manual) {
                $locked->suggested_price = $this->money2($productsSaleTotal);
                if ($forceSuggested) {
                    $locked->suggested_price_is_manual = false;
                }
            }

            if ($forceMin || ! $locked->min_price_is_manual) {
                $locked->min_price = $this->money2($minFromProducts);
                if ($forceMin) {
                    $locked->min_price_is_manual = false;
                }
            }

            $locked->save();

            return $locked->fresh(['items.product']);
        });
    }

    public function syncItems(Protocol $protocol, array $items): Protocol
    {
        return DB::transaction(function () use ($protocol, $items) {
            $protocol->items()->delete();

            foreach ($items as $item) {
                $protocol->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $this->recalculate($protocol);
        });
    }

    private function money2(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function money4(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
