<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Protocol;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalePricingService
{
    public function syncItems(Sale $sale, array $items): Sale
    {
        $this->assertDraft($sale);

        return DB::transaction(function () use ($sale, $items) {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            $merged = [];
            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                if (isset($merged[$productId])) {
                    $merged[$productId]['quantity'] = (float) $merged[$productId]['quantity'] + (float) $item['quantity'];
                    if (array_key_exists('unit_price', $item) && $item['unit_price'] !== null) {
                        $merged[$productId]['unit_price'] = $item['unit_price'];
                    }
                    if (array_key_exists('source_protocol_id', $item)) {
                        $merged[$productId]['source_protocol_id'] = $item['source_protocol_id'];
                    }
                } else {
                    $merged[$productId] = $item;
                }
            }

            $locked->items()->delete();

            foreach ($merged as $productId => $item) {
                $product = Product::query()->whereKey($productId)->firstOrFail();
                $this->createItemFromProduct(
                    $locked,
                    $product,
                    (float) $item['quantity'],
                    array_key_exists('unit_price', $item) ? $item['unit_price'] : null,
                    $item['source_protocol_id'] ?? null,
                );
            }

            return $this->recalculateTotals($locked);
        });
    }

    public function applyProtocol(Sale $sale, Protocol $protocol): Sale
    {
        $this->assertDraft($sale);

        if ((int) $protocol->clinic_id !== (int) $sale->clinic_id) {
            throw ValidationException::withMessages([
                'protocol_id' => ['Protocol does not belong to the current clinic.'],
            ]);
        }

        return DB::transaction(function () use ($sale, $protocol) {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $protocol->loadMissing('items.product');

            foreach ($protocol->items as $protocolItem) {
                $product = $protocolItem->product;
                $existing = $locked->items()->where('product_id', $product->id)->first();

                if ($existing !== null) {
                    $newQty = (float) $existing->quantity + (float) $protocolItem->quantity;
                    $existing->update([
                        'quantity' => $this->qty($newQty),
                        'list_line_total' => $this->money2($newQty * (float) $existing->list_unit_price),
                        'line_total' => $this->money2($newQty * (float) $existing->unit_price),
                        'source_protocol_id' => $existing->source_protocol_id ?? $protocol->id,
                    ]);
                } else {
                    $this->createItemFromProduct(
                        $locked,
                        $product,
                        (float) $protocolItem->quantity,
                        null,
                        $protocol->id,
                    );
                }
            }

            return $this->recalculateTotals($locked->fresh());
        });
    }

    public function syncPayments(Sale $sale, array $payments): Sale
    {
        $this->assertDraft($sale);

        return DB::transaction(function () use ($sale, $payments) {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $locked->payments()->delete();

            foreach ($payments as $payment) {
                /** @var PaymentMethod $method */
                $method = PaymentMethod::query()->whereKey($payment['payment_method_id'])->firstOrFail();

                if ($method->requires_card_meta) {
                    if (empty($payment['card_operator_id']) || empty($payment['card_brand_id']) || empty($payment['installments'])) {
                        throw ValidationException::withMessages([
                            'payments' => ['Card payments require card_operator_id, card_brand_id and installments.'],
                        ]);
                    }
                }

                $locked->payments()->create([
                    'payment_method_id' => $method->id,
                    'amount' => $this->money2((float) $payment['amount']),
                    'card_operator_id' => $payment['card_operator_id'] ?? null,
                    'card_brand_id' => $payment['card_brand_id'] ?? null,
                    'installments' => $payment['installments'] ?? null,
                    'paid_at' => $payment['paid_at'] ?? null,
                ]);
            }

            return $locked->fresh(['items.product', 'payments.paymentMethod', 'client', 'soldByUser']);
        });
    }

    public function recalculateTotals(Sale $sale): Sale
    {
        $sale->load('items');

        $expected = $sale->items->sum(fn (SaleItem $item) => (float) $item->line_total);
        $sale->expected_amount = $this->money2($expected);

        if (! $sale->effective_amount_is_manual) {
            $sale->effective_amount = $sale->expected_amount;
        }

        $sale->save();

        return $sale->fresh(['items.product', 'items.sourceProtocol', 'payments.paymentMethod', 'client', 'soldByUser']);
    }

    public function setEffectiveAmount(Sale $sale, float|string $amount): Sale
    {
        $this->assertDraft($sale);

        $sale->effective_amount = $this->money2((float) $amount);
        $sale->effective_amount_is_manual = true;
        $sale->save();

        return $sale->fresh(['items.product', 'items.sourceProtocol', 'payments.paymentMethod', 'client', 'soldByUser']);
    }

    public function confirm(Sale $sale, bool $confirmBelowMinimum = false): Sale
    {
        $this->assertDraft($sale);
        $sale->load(['items', 'payments']);

        if ($sale->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Sale must have at least one item before confirmation.'],
            ]);
        }

        $paymentsTotal = (float) $sale->paymentsTotal();
        $effective = (float) $sale->effective_amount;

        if (abs($paymentsTotal - $effective) > 0.001) {
            throw ValidationException::withMessages([
                'payments' => ['Payment amounts must equal the effective sale amount.'],
            ]);
        }

        if ($sale->isBelowMinimum() && ! $confirmBelowMinimum) {
            throw ValidationException::withMessages([
                'confirm_below_minimum' => [
                    'Effective amount is below the minimum. Pass confirm_below_minimum=true to proceed.',
                ],
                'min_amount' => [$sale->minAmount()],
                'effective_amount' => [$sale->effective_amount],
            ]);
        }

        $sale->update([
            'status' => Sale::STATUS_CONFIRMED,
            'sold_at' => $sale->sold_at ?? now(),
        ]);

        return $sale->fresh(['items.product', 'items.sourceProtocol', 'payments.paymentMethod', 'client', 'soldByUser']);
    }

    public function cancel(Sale $sale): Sale
    {
        if ($sale->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => ['Sale is already cancelled.'],
            ]);
        }

        $sale->update(['status' => Sale::STATUS_CANCELLED]);

        return $sale->fresh(['items.product', 'items.sourceProtocol', 'payments.paymentMethod', 'client', 'soldByUser']);
    }

    private function createItemFromProduct(
        Sale $sale,
        Product $product,
        float $quantity,
        float|string|null $unitPrice,
        ?int $sourceProtocolId,
    ): SaleItem {
        $listPrice = (float) $product->sale_price;
        $price = $unitPrice !== null ? (float) $unitPrice : $listPrice;
        $cost = (float) $product->cost;
        $min = $product->min_sale_price !== null
            ? (float) $product->min_sale_price
            : $cost;

        return $sale->items()->create([
            'product_id' => $product->id,
            'source_protocol_id' => $sourceProtocolId,
            'product_name' => $product->name,
            'quantity' => $this->qty($quantity),
            'list_unit_price' => $this->money2($listPrice),
            'list_line_total' => $this->money2($listPrice * $quantity),
            'unit_price' => $this->money2($price),
            'unit_cost' => $this->money4($cost),
            'min_unit_price' => $this->money2($min),
            'line_total' => $this->money2($price * $quantity),
        ]);
    }

    private function assertDraft(Sale $sale): void
    {
        if (! $sale->isDraft()) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales can be modified.'],
            ]);
        }
    }

    private function money2(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function money4(float $value): string
    {
        return number_format($value, 4, '.', '');
    }

    private function qty(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
