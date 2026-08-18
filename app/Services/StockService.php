<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    public function move(
        Product $product,
        StockMovementType $type,
        string $quantity,
        ?string $unitCost = null,
        ?string $reason = null,
        ?string $notes = null,
        ?User $user = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $type, $quantity, $unitCost, $reason, $notes, $user) {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $qty = $this->decimal($quantity);
            if ($qty <= 0) {
                throw new InvalidArgumentException('Quantity must be greater than zero.');
            }

            $stockBefore = $this->decimal($locked->stock_quantity);
            $costBefore = $this->decimal($locked->cost);

            if ($type === StockMovementType::In) {
                if ($unitCost === null) {
                    throw new InvalidArgumentException('unit_cost is required for stock inbound.');
                }

                $inboundCost = $this->decimal($unitCost);
                if ($inboundCost < 0) {
                    throw new InvalidArgumentException('unit_cost cannot be negative.');
                }

                $stockAfter = $stockBefore + $qty;
                $costAfter = $stockBefore <= 0
                    ? $inboundCost
                    : (($stockBefore * $costBefore) + ($qty * $inboundCost)) / ($stockBefore + $qty);
            } else {
                $stockAfter = $stockBefore - $qty;
                if ($stockAfter < 0) {
                    throw new InvalidArgumentException('Insufficient stock for this movement.');
                }
                $costAfter = $costBefore;
            }

            $locked->stock_quantity = $this->format($stockAfter);
            $locked->cost = $this->format($costAfter);
            $locked->save();

            return StockMovement::query()->create([
                'clinic_id' => $locked->clinic_id,
                'product_id' => $locked->id,
                'user_id' => $user?->id,
                'type' => $type->value,
                'quantity' => $this->format($qty),
                'unit_cost' => $unitCost !== null ? $this->format($this->decimal($unitCost)) : null,
                'cost_before' => $this->format($costBefore),
                'cost_after' => $this->format($costAfter),
                'stock_before' => $this->format($stockBefore),
                'stock_after' => $this->format($stockAfter),
                'reason' => $reason,
                'notes' => $notes,
            ]);
        });
    }

    private function decimal(string|float|int $value): float
    {
        return round((float) $value, 4);
    }

    private function format(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
