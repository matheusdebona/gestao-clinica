<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PatientReturn;
use App\Models\Protocol;
use App\Models\Sale;
use App\Models\Treatment;
use App\Models\User;
use App\Support\ClinicMonth;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Patient return reminders ("Retornos").
 *
 * The suggested return lives on the PROTOCOL (protocols.return_after_days): a protocol is the
 * procedure the patient buys (e.g. toxina, preenchimento) and sale items keep source_protocol_id,
 * so the return follows what was actually sold. Products are supplies and treatments are the
 * per-sale clinical case, so neither is the natural place for "volta em X dias".
 *
 * - Sale confirmed → one return per protocol sold with return_after_days (due = sale day + X).
 * - Treatment completed → its sale's returns are re-dated from the completion day (+ X).
 * - Done automatically when the patient books a new appointment (outside that sale's own
 *   treatment, scheduled from RETURN_WINDOW_DAYS before the due date on) or has a new confirmed
 *   sale; or manually. Cancelling the sale cancels its pending returns.
 * Dates are America/Sao_Paulo calendar days.
 */
class PatientReturnService
{
    public const TZ = ClinicMonth::TZ;

    /** "Vence em breve": due within this many days. */
    public const DUE_SOON_DAYS = 7;

    /** An appointment scheduled from this many days before the due date counts as the return. */
    public const RETURN_WINDOW_DAYS = 30;

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ)->startOfDay();
    }

    public function onSaleConfirmed(Sale $sale): void
    {
        $soldOn = CarbonImmutable::parse($sale->sold_at ?? now())->setTimezone(self::TZ)->startOfDay();

        // A new purchase closes the patient's earlier pending returns.
        PatientReturn::query()
            ->where('client_id', $sale->client_id)
            ->where('status', PatientReturn::STATUS_PENDING)
            ->where(fn (Builder $q) => $q->whereNull('sale_id')->orWhere('sale_id', '!=', $sale->id))
            ->get()
            ->each(fn (PatientReturn $return) => $this->close($return, PatientReturn::REASON_SALE, null));

        $this->upsertForSale($sale, $soldOn, null);
    }

    public function onSaleCancelled(Sale $sale): void
    {
        PatientReturn::query()
            ->where('sale_id', $sale->id)
            ->where('status', PatientReturn::STATUS_PENDING)
            ->update(['status' => PatientReturn::STATUS_CANCELLED, 'done_at' => now()]);
    }

    public function onTreatmentCompleted(Treatment $treatment): void
    {
        $sale = $treatment->sale_id ? Sale::query()->find($treatment->sale_id) : null;
        if ($sale === null || $sale->status !== Sale::STATUS_CONFIRMED) {
            return;
        }
        $this->upsertForSale($sale, self::today(), $treatment);
    }

    public function onAppointmentCreated(Appointment $appointment): void
    {
        if ($appointment->scheduled_at === null || $appointment->status === Appointment::STATUS_CANCELLED) {
            return;
        }
        $scheduledOn = CarbonImmutable::parse($appointment->scheduled_at)->setTimezone(self::TZ)->startOfDay();
        $ownSaleId = $appointment->treatment_id
            ? Treatment::query()->whereKey($appointment->treatment_id)->value('sale_id')
            : null;

        PatientReturn::query()
            ->where('client_id', $appointment->client_id)
            ->where('status', PatientReturn::STATUS_PENDING)
            ->whereDate('due_on', '<=', $scheduledOn->addDays(self::RETURN_WINDOW_DAYS)->toDateString())
            ->when($ownSaleId !== null, fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNull('sale_id')->orWhere('sale_id', '!=', $ownSaleId)))
            ->get()
            ->each(fn (PatientReturn $return) => $this->close($return, PatientReturn::REASON_APPOINTMENT, null));
    }

    public function markDone(PatientReturn $return, User $by): PatientReturn
    {
        return $this->close($return, PatientReturn::REASON_MANUAL, $by);
    }

    public function logWhatsApp(PatientReturn $return, User $by): PatientReturn
    {
        $return->forceFill(['whatsapp_sent_at' => now(), 'whatsapp_sent_by_user_id' => $by->id])->save();

        return $return;
    }

    /**
     * Pending returns due within DUE_SOON_DAYS or overdue (clinic-scoped).
     *
     * @param  list<int>  $soldByUserIds  optional: only returns of sales by these users
     */
    public function dueQuery(?CarbonInterface $today = null, array $soldByUserIds = []): Builder
    {
        $today = CarbonImmutable::parse($today ?? self::today());

        return PatientReturn::query()
            ->where('patient_returns.status', PatientReturn::STATUS_PENDING)
            ->whereDate('patient_returns.due_on', '<=', $today->addDays(self::DUE_SOON_DAYS)->toDateString())
            ->when($soldByUserIds !== [], fn (Builder $q) => $q->whereIn(
                'patient_returns.sale_id',
                Sale::query()->select('sales.id')->whereIn('sales.sold_by_user_id', $soldByUserIds),
            ))
            ->orderBy('patient_returns.due_on')
            ->orderBy('patient_returns.id');
    }

    /**
     * @return array{count: int, overdue: int, items: list<array<string, mixed>>}
     */
    public function dueSummary(int $limit, array $soldByUserIds = []): array
    {
        $today = self::today();
        $query = $this->dueQuery($today, $soldByUserIds);
        $count = (clone $query)->count();
        $overdue = (clone $query)->whereDate('patient_returns.due_on', '<', $today->toDateString())->count();
        $items = $query->with('client')->limit($limit)->get()
            ->map(fn (PatientReturn $r) => $this->present($r, $today))->values()->all();

        return ['count' => $count, 'overdue' => $overdue, 'items' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PatientReturn $return, ?CarbonImmutable $today = null): array
    {
        $today ??= self::today();
        $due = CarbonImmutable::parse($return->due_on->format('Y-m-d'), self::TZ);

        return [
            'id' => $return->id,
            'client_id' => $return->client_id,
            'client' => $return->relationLoaded('client') && $return->client ? [
                'id' => $return->client->id,
                'name' => $return->client->name,
                'whatsapp' => $return->client->whatsapp,
            ] : null,
            'protocol_id' => $return->protocol_id,
            'sale_id' => $return->sale_id,
            'treatment_id' => $return->treatment_id,
            'label' => $return->label,
            'due_on' => $due->toDateString(),
            'days_until_due' => (int) $today->diffInDays($due, false),
            'status' => $return->status,
            'done_at' => $return->done_at,
            'done_reason' => $return->done_reason,
            'whatsapp_sent_at' => $return->whatsapp_sent_at,
        ];
    }

    private function upsertForSale(Sale $sale, CarbonImmutable $from, ?Treatment $treatment): void
    {
        $protocols = $this->returnProtocols($sale);
        foreach ($protocols as $protocol) {
            $due = $from->addDays((int) $protocol->return_after_days)->toDateString();
            $existing = PatientReturn::query()->where('sale_id', $sale->id)->where('protocol_id', $protocol->id)->first();
            if ($existing !== null) {
                if ($existing->isPending()) {
                    $existing->forceFill(['due_on' => $due, 'treatment_id' => $treatment?->id ?? $existing->treatment_id])->save();
                }

                continue;
            }
            PatientReturn::query()->create([
                'clinic_id' => $sale->clinic_id,
                'client_id' => $sale->client_id,
                'protocol_id' => $protocol->id,
                'sale_id' => $sale->id,
                'treatment_id' => $treatment?->id,
                'label' => $protocol->name,
                'due_on' => $due,
            ]);
        }
    }

    /**
     * @return Collection<int, Protocol>
     */
    private function returnProtocols(Sale $sale): Collection
    {
        $ids = $sale->items()->whereNotNull('source_protocol_id')->distinct()->pluck('source_protocol_id');

        return Protocol::query()->whereIn('id', $ids)->whereNotNull('return_after_days')->where('return_after_days', '>', 0)->get();
    }

    private function close(PatientReturn $return, string $reason, ?User $by): PatientReturn
    {
        $return->forceFill([
            'status' => PatientReturn::STATUS_DONE,
            'done_at' => now(),
            'done_reason' => $reason,
            'done_by_user_id' => $by?->id,
        ])->save();

        return $return;
    }
}
