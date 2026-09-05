<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Appointment;
use App\Models\AppointmentConsumption;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function __construct(
        private readonly TreatmentService $treatments,
        private readonly StockService $stock,
        private readonly StockAlertService $stockAlerts,
    ) {}

    /**
     * @param  array{scheduled_at?: string|null, professional_user_id?: int|null, notes?: string|null}  $data
     * @return array{appointment: Appointment, warnings: list<string>}
     */
    public function schedule(Treatment $treatment, array $data): array
    {
        if (! $treatment->isOpen()) {
            throw ValidationException::withMessages([
                'treatment' => ['Appointments can only be scheduled on open treatments.'],
            ]);
        }

        $appointment = Appointment::query()->create([
            'clinic_id' => $treatment->clinic_id,
            'treatment_id' => $treatment->id,
            'client_id' => $treatment->client_id,
            'professional_user_id' => $data['professional_user_id'] ?? null,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $suggested = $this->treatments->suggestedConsumptions($treatment);
        $warnings = $this->stockAlerts->warningMessagesForSuggested($suggested);
        $this->stockAlerts->notifyAppointmentWarnings($appointment, $warnings);

        return [
            'appointment' => $appointment->fresh(['consumptions', 'treatment', 'client']),
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array{scheduled_at?: string|null, professional_user_id?: int|null, notes?: string|null}  $data
     */
    public function update(Appointment $appointment, array $data): Appointment
    {
        if (! $appointment->isScheduled()) {
            throw ValidationException::withMessages([
                'appointment' => ['Only scheduled appointments can be updated.'],
            ]);
        }

        $appointment->fill([
            'scheduled_at' => array_key_exists('scheduled_at', $data) ? $data['scheduled_at'] : $appointment->scheduled_at,
            'professional_user_id' => array_key_exists('professional_user_id', $data)
                ? $data['professional_user_id']
                : $appointment->professional_user_id,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $appointment->notes,
        ]);
        $appointment->save();

        return $appointment->fresh(['consumptions', 'treatment', 'client']);
    }

    /**
     * @return array{appointment: Appointment, suggested_consumptions: list<array<string, mixed>>, stock_warnings: list<array<string, mixed>>}
     */
    public function start(Appointment $appointment): array
    {
        if (! $appointment->isScheduled()) {
            throw ValidationException::withMessages([
                'appointment' => ['Only scheduled appointments can be started.'],
            ]);
        }

        $appointment->loadMissing('treatment');

        if (! $appointment->treatment->isOpen()) {
            throw ValidationException::withMessages([
                'appointment' => ['Cannot start an appointment on a closed treatment.'],
            ]);
        }

        $suggested = $this->treatments->suggestedConsumptions($appointment->treatment);
        $warnings = $this->treatments->stockWarningsForSuggested($suggested);

        $appointment->status = Appointment::STATUS_IN_PROGRESS;
        $appointment->started_at = now();
        $appointment->stock_warning = $warnings !== [] ? $warnings : null;
        $appointment->save();

        return [
            'appointment' => $appointment->fresh(['consumptions', 'treatment', 'client', 'professionalUser']),
            'suggested_consumptions' => $suggested,
            'stock_warnings' => $warnings,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function syncConsumptions(Appointment $appointment, array $lines): Appointment
    {
        if (! $appointment->isInProgress()) {
            throw ValidationException::withMessages([
                'appointment' => ['Consumptions can only be synced while the appointment is in progress.'],
            ]);
        }

        $appointment->loadMissing('treatment.sale');

        return DB::transaction(function () use ($appointment, $lines) {
            $previousPaymentIds = $appointment->consumptions()
                ->whereNotNull('sale_payment_id')
                ->pluck('sale_payment_id')
                ->all();

            $appointment->consumptions()->delete();

            if ($previousPaymentIds !== []) {
                SalePayment::query()->whereIn('id', $previousPaymentIds)->delete();
            }

            $sale = $appointment->treatment->sale;

            foreach ($lines as $index => $line) {
                $source = $line['source'];
                $quantity = number_format((float) $line['quantity'], 4, '.', '');
                if ((float) $quantity <= 0) {
                    throw ValidationException::withMessages([
                        "consumptions.{$index}.quantity" => ['Quantity must be greater than zero.'],
                    ]);
                }

                $product = Product::query()->whereKey($line['product_id'])->firstOrFail();
                $saleItemId = null;
                $isComplimentary = (bool) ($line['is_complimentary'] ?? false);
                $chargedAmount = number_format((float) ($line['charged_amount'] ?? 0), 2, '.', '');
                $salePaymentId = null;

                if ($source === AppointmentConsumption::SOURCE_SUGGESTED) {
                    $saleItemId = (int) $line['sale_item_id'];
                    /** @var SaleItem $saleItem */
                    $saleItem = SaleItem::query()->whereKey($saleItemId)->firstOrFail();
                    if ($saleItem->sale_id !== $sale->id) {
                        throw ValidationException::withMessages([
                            "consumptions.{$index}.sale_item_id" => ['Sale item does not belong to this treatment sale.'],
                        ]);
                    }
                    if ((int) $saleItem->product_id !== (int) $product->id) {
                        throw ValidationException::withMessages([
                            "consumptions.{$index}.product_id" => ['Product must match the sale item product.'],
                        ]);
                    }
                    $isComplimentary = false;
                    $chargedAmount = '0.00';
                } elseif ($isComplimentary) {
                    $chargedAmount = '0.00';
                } else {
                    if ((float) $chargedAmount <= 0) {
                        throw ValidationException::withMessages([
                            "consumptions.{$index}.charged_amount" => ['Charged extras require charged_amount > 0.'],
                        ]);
                    }

                    $paymentPayload = $line['payment'] ?? null;
                    if (! is_array($paymentPayload) || empty($paymentPayload['payment_method_id'])) {
                        throw ValidationException::withMessages([
                            "consumptions.{$index}.payment" => ['Charged extras require payment details.'],
                        ]);
                    }

                    $salePaymentId = $this->createExtraPayment($sale->id, $chargedAmount, $paymentPayload, $index)->id;
                }

                $appointment->consumptions()->create([
                    'product_id' => $product->id,
                    'sale_item_id' => $saleItemId,
                    'source' => $source,
                    'quantity' => $quantity,
                    'is_complimentary' => $isComplimentary,
                    'charged_amount' => $chargedAmount,
                    'sale_payment_id' => $salePaymentId,
                ]);
            }

            return $appointment->fresh(['consumptions.product', 'consumptions.salePayment', 'treatment', 'client']);
        });
    }

    public function complete(Appointment $appointment, ?User $user = null): Appointment
    {
        if (! $appointment->isInProgress()) {
            throw ValidationException::withMessages([
                'appointment' => ['Only in-progress appointments can be completed.'],
            ]);
        }

        return DB::transaction(function () use ($appointment, $user) {
            /** @var Appointment $locked */
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $locked->load(['consumptions.product', 'treatment']);

            $totalCost = 0.0;
            $totalCharged = 0.0;

            foreach ($locked->consumptions as $consumption) {
                $product = $consumption->product;
                $unitCost = number_format((float) $product->cost, 4, '.', '');
                $lineCost = number_format((float) $consumption->quantity * (float) $unitCost, 4, '.', '');

                $consumption->unit_cost = $unitCost;
                $consumption->line_cost = $lineCost;
                $consumption->save();

                $totalCost += (float) $lineCost;
                $totalCharged += (float) $consumption->charged_amount;

                $this->stock->move(
                    product: $product,
                    type: StockMovementType::Out,
                    quantity: (string) $consumption->quantity,
                    unitCost: null,
                    reason: 'appointment_complete',
                    notes: "Appointment #{$locked->id}",
                    user: $user,
                    allowNegative: true,
                    reference: $locked,
                );
            }

            $finishedAt = now();
            $duration = $locked->started_at
                ? (int) $locked->started_at->diffInMinutes($finishedAt)
                : null;

            $locked->status = Appointment::STATUS_COMPLETED;
            $locked->finished_at = $finishedAt;
            $locked->duration_minutes = $duration;
            $locked->total_cost = number_format($totalCost, 4, '.', '');
            $locked->total_charged_on_appointment = number_format($totalCharged, 2, '.', '');
            $locked->save();

            $this->treatments->recalculateTotalCost($locked->treatment);

            return $locked->fresh(['consumptions.product', 'consumptions.salePayment', 'treatment', 'client', 'professionalUser']);
        });
    }

    public function cancel(Appointment $appointment): Appointment
    {
        if (! in_array($appointment->status, [Appointment::STATUS_SCHEDULED, Appointment::STATUS_IN_PROGRESS], true)) {
            throw ValidationException::withMessages([
                'appointment' => ['Only scheduled or in-progress appointments can be cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($appointment) {
            $paymentIds = $appointment->consumptions()
                ->whereNotNull('sale_payment_id')
                ->pluck('sale_payment_id')
                ->all();

            $appointment->consumptions()->delete();

            if ($paymentIds !== []) {
                SalePayment::query()->whereIn('id', $paymentIds)->delete();
            }

            $appointment->status = Appointment::STATUS_CANCELLED;
            $appointment->save();

            return $appointment->fresh(['treatment', 'client']);
        });
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function createExtraPayment(int $saleId, string $amount, array $payment, int $index): SalePayment
    {
        /** @var PaymentMethod $method */
        $method = PaymentMethod::query()->whereKey($payment['payment_method_id'])->firstOrFail();

        if ($method->requires_card_meta) {
            if (empty($payment['card_operator_id']) || empty($payment['card_brand_id']) || empty($payment['installments'])) {
                throw ValidationException::withMessages([
                    "consumptions.{$index}.payment" => ['Card payments require card_operator_id, card_brand_id and installments.'],
                ]);
            }
        }

        return SalePayment::query()->create([
            'sale_id' => $saleId,
            'payment_method_id' => $method->id,
            'amount' => $amount,
            'card_operator_id' => $payment['card_operator_id'] ?? null,
            'card_brand_id' => $payment['card_brand_id'] ?? null,
            'installments' => $payment['installments'] ?? null,
            'paid_at' => $payment['paid_at'] ?? now(),
        ]);
    }
}
