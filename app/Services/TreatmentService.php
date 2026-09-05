<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentConsumption;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TreatmentService
{
    public function openFromSale(Sale $sale, User $user, ?string $notes = null): Treatment
    {
        if (! $sale->isConfirmed()) {
            throw ValidationException::withMessages([
                'sale' => ['Treatment can only be opened from a confirmed sale.'],
            ]);
        }

        if ($sale->treatment()->exists()) {
            throw ValidationException::withMessages([
                'sale' => ['A treatment already exists for this sale.'],
            ]);
        }

        return Treatment::query()->create([
            'clinic_id' => $sale->clinic_id,
            'sale_id' => $sale->id,
            'client_id' => $sale->client_id,
            'opened_by_user_id' => $user->id,
            'status' => Treatment::STATUS_OPEN,
            'notes' => $notes,
        ]);
    }

    /**
     * @return list<array{sale_item_id: int, product_id: int, product_name: string, sold_quantity: string, consumed_quantity: string, remaining_quantity: string, stock_quantity: string}>
     */
    public function fulfillment(Treatment $treatment): array
    {
        $treatment->loadMissing(['sale.items.product']);
        $consumedBySaleItem = $this->consumedQuantitiesBySaleItem($treatment);

        return $treatment->sale->items->map(function (SaleItem $item) use ($consumedBySaleItem) {
            $sold = (float) $item->quantity;
            $consumed = (float) ($consumedBySaleItem[$item->id] ?? 0);
            $remaining = max(0, round($sold - $consumed, 4));

            return [
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'sold_quantity' => number_format($sold, 4, '.', ''),
                'consumed_quantity' => number_format($consumed, 4, '.', ''),
                'remaining_quantity' => number_format($remaining, 4, '.', ''),
                'stock_quantity' => number_format((float) ($item->product?->stock_quantity ?? 0), 4, '.', ''),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{sale_item_id: int, product_id: int, product_name: string, quantity: string, stock_quantity: string}>
     */
    public function suggestedConsumptions(Treatment $treatment): array
    {
        return collect($this->fulfillment($treatment))
            ->filter(fn (array $row) => (float) $row['remaining_quantity'] > 0)
            ->map(fn (array $row) => [
                'sale_item_id' => $row['sale_item_id'],
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'quantity' => $row['remaining_quantity'],
                'stock_quantity' => $row['stock_quantity'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{sale_item_id: int, product_id: int, product_name: string, quantity: string, stock_quantity: string}>  $suggested
     * @return list<array{product_id: int, product_name: string, suggested_quantity: string, stock_quantity: string}>
     */
    public function stockWarningsForSuggested(array $suggested): array
    {
        $warnings = [];

        foreach ($suggested as $row) {
            if ((float) $row['stock_quantity'] < (float) $row['quantity']) {
                $warnings[] = [
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'suggested_quantity' => $row['quantity'],
                    'stock_quantity' => $row['stock_quantity'],
                ];
            }
        }

        return $warnings;
    }

    public function complete(Treatment $treatment): Treatment
    {
        if (! $treatment->isOpen()) {
            throw ValidationException::withMessages([
                'treatment' => ['Only open treatments can be completed.'],
            ]);
        }

        $active = $treatment->appointments()
            ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_IN_PROGRESS])
            ->exists();

        if ($active) {
            throw ValidationException::withMessages([
                'treatment' => ['Cancel or complete all appointments before completing the treatment.'],
            ]);
        }

        $treatment->status = Treatment::STATUS_COMPLETED;
        $treatment->save();

        return $treatment->fresh(['appointments.consumptions', 'client', 'sale']);
    }

    public function cancel(Treatment $treatment): Treatment
    {
        if (! $treatment->isOpen()) {
            throw ValidationException::withMessages([
                'treatment' => ['Only open treatments can be cancelled.'],
            ]);
        }

        $hasCompleted = $treatment->appointments()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->exists();

        if ($hasCompleted) {
            throw ValidationException::withMessages([
                'treatment' => ['Cannot cancel a treatment that has completed appointments.'],
            ]);
        }

        return DB::transaction(function () use ($treatment) {
            $treatment->appointments()
                ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_IN_PROGRESS])
                ->update(['status' => Appointment::STATUS_CANCELLED]);

            $treatment->status = Treatment::STATUS_CANCELLED;
            $treatment->save();

            return $treatment->fresh(['appointments', 'client', 'sale']);
        });
    }

    public function recalculateTotalCost(Treatment $treatment): void
    {
        $total = (float) $treatment->appointments()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->sum('total_cost');

        $treatment->total_cost = number_format($total, 4, '.', '');
        $treatment->save();
    }

    /**
     * @return array<int, float>
     */
    private function consumedQuantitiesBySaleItem(Treatment $treatment): array
    {
        $rows = AppointmentConsumption::query()
            ->whereNotNull('sale_item_id')
            ->whereHas('appointment', function ($q) use ($treatment) {
                $q->where('treatment_id', $treatment->id)
                    ->where('status', Appointment::STATUS_COMPLETED);
            })
            ->get(['sale_item_id', 'quantity']);

        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row->sale_item_id;
            $map[$id] = ($map[$id] ?? 0) + (float) $row->quantity;
        }

        return $map;
    }
}
