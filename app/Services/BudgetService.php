<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    public function createFromSale(Sale $sale, int $userId, ?string $notes = null, mixed $validUntil = null): Budget
    {
        if (! $sale->isDraft()) {
            throw ValidationException::withMessages([
                'sale_id' => ['Budgets can only be created from draft sales.'],
            ]);
        }

        $sale->loadMissing('items');

        if ($sale->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Sale must have at least one item before creating a budget.'],
            ]);
        }

        return DB::transaction(function () use ($sale, $userId, $notes, $validUntil) {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $locked->load('items');

            Budget::query()
                ->where('sale_id', $locked->id)
                ->whereIn('status', [Budget::STATUS_DRAFT, Budget::STATUS_SENT])
                ->update(['status' => Budget::STATUS_SUPERSEDED]);

            $nextVersion = (int) Budget::query()->where('sale_id', $locked->id)->max('version') + 1;

            $budget = Budget::query()->create([
                'clinic_id' => $locked->clinic_id,
                'sale_id' => $locked->id,
                'client_id' => $locked->client_id,
                'created_by_user_id' => $userId,
                'version' => $nextVersion,
                'status' => Budget::STATUS_DRAFT,
                'expected_amount' => $locked->expected_amount,
                'effective_amount' => $locked->effective_amount,
                'min_amount' => $locked->minAmount(),
                'notes' => $notes,
                'valid_until' => $validUntil,
            ]);

            foreach ($locked->items as $item) {
                /** @var SaleItem $item */
                $budget->items()->create([
                    'product_id' => $item->product_id,
                    'source_protocol_id' => $item->source_protocol_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'list_unit_price' => $item->list_unit_price,
                    'list_line_total' => $item->list_line_total,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                    'unit_cost' => $item->unit_cost,
                    'min_unit_price' => $item->min_unit_price,
                ]);
            }

            return $budget->fresh(['items', 'client', 'sale', 'createdByUser']);
        });
    }

    public function updateDraft(Budget $budget, array $data): Budget
    {
        if (! $budget->isDraft()) {
            throw ValidationException::withMessages([
                'status' => ['Only draft budgets can be updated.'],
            ]);
        }

        $budget->update(array_intersect_key($data, array_flip(['notes', 'valid_until'])));

        return $budget->fresh(['items', 'client', 'sale', 'createdByUser']);
    }

    public function send(Budget $budget): Budget
    {
        if (! $budget->isDraft()) {
            throw ValidationException::withMessages([
                'status' => ['Only draft budgets can be sent.'],
            ]);
        }

        $budget->update([
            'status' => Budget::STATUS_SENT,
            'sent_at' => now(),
        ]);

        return $budget->fresh(['items', 'client', 'sale', 'createdByUser']);
    }

    public function accept(Budget $budget): Budget
    {
        if (! $budget->isSent()) {
            throw ValidationException::withMessages([
                'status' => ['Only sent budgets can be accepted.'],
            ]);
        }

        return DB::transaction(function () use ($budget) {
            /** @var Budget $locked */
            $locked = Budget::query()->whereKey($budget->id)->lockForUpdate()->firstOrFail();

            $alreadyAccepted = Budget::query()
                ->where('sale_id', $locked->sale_id)
                ->where('status', Budget::STATUS_ACCEPTED)
                ->exists();

            if ($alreadyAccepted) {
                throw ValidationException::withMessages([
                    'status' => ['This sale already has an accepted budget.'],
                ]);
            }

            $locked->update([
                'status' => Budget::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            return $locked->fresh(['items', 'client', 'sale', 'createdByUser']);
        });
    }

    public function reject(Budget $budget): Budget
    {
        if (! in_array($budget->status, [Budget::STATUS_DRAFT, Budget::STATUS_SENT], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or sent budgets can be rejected.'],
            ]);
        }

        $budget->update([
            'status' => Budget::STATUS_REJECTED,
            'rejected_at' => now(),
        ]);

        return $budget->fresh(['items', 'client', 'sale', 'createdByUser']);
    }

    public function expire(Budget $budget): Budget
    {
        if (! in_array($budget->status, [Budget::STATUS_DRAFT, Budget::STATUS_SENT], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or sent budgets can be expired.'],
            ]);
        }

        $budget->update([
            'status' => Budget::STATUS_EXPIRED,
        ]);

        return $budget->fresh(['items', 'client', 'sale', 'createdByUser']);
    }
}
