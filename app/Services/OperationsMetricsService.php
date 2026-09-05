<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentConsumption;
use App\Models\SaleItem;
use App\Models\Treatment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OperationsMetricsService
{
    private const PENDING_LIST_LIMIT = 100;

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     date_field: string,
     *     kpis: array<string, mixed>,
     *     sessions_by_status: array<string, int>,
     *     pending_fulfillments: list<array<string, mixed>>,
     *     by_professional: list<array<string, mixed>>,
     *     notes: list<string>
     * }
     */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $fromStart = $from->startOfDay();
        $toEnd = $to->endOfDay();

        $sessionsByStatus = [
            Appointment::STATUS_SCHEDULED => 0,
            Appointment::STATUS_IN_PROGRESS => 0,
            Appointment::STATUS_COMPLETED => 0,
            Appointment::STATUS_CANCELLED => 0,
        ];

        $counts = Appointment::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$fromStart, $toEnd])
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        foreach ($counts as $status => $count) {
            if (array_key_exists($status, $sessionsByStatus)) {
                $sessionsByStatus[$status] = (int) $count;
            }
        }

        $totalSessions = array_sum($sessionsByStatus);
        $cancelled = $sessionsByStatus[Appointment::STATUS_CANCELLED];
        $cancellationRate = $totalSessions > 0
            ? number_format(($cancelled / $totalSessions) * 100, 2, '.', '')
            : null;

        $pending = $this->pendingFulfillments();

        $byProfessional = Appointment::query()
            ->select(
                'professional_user_id',
                DB::raw('COUNT(*) as sessions_count'),
                DB::raw('COALESCE(SUM(total_cost), 0) as total_cost'),
            )
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$fromStart, $toEnd])
            ->whereNotNull('professional_user_id')
            ->groupBy('professional_user_id')
            ->orderByDesc('sessions_count')
            ->get();

        $professionals = User::query()
            ->whereIn('id', $byProfessional->pluck('professional_user_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $byProfessionalRows = $byProfessional->map(function ($row) use ($professionals) {
            $user = $professionals->get($row->professional_user_id);

            return [
                'professional_user_id' => (int) $row->professional_user_id,
                'name' => $user?->name,
                'sessions_count' => (int) $row->sessions_count,
                'total_cost' => number_format((float) $row->total_cost, 4, '.', ''),
            ];
        })->values()->all();

        return [
            'from' => $fromStart->toDateString(),
            'to' => $toEnd->toDateString(),
            'date_field' => 'scheduled_at',
            'kpis' => [
                'sessions_total' => $totalSessions,
                'cancellation_rate' => $cancellationRate,
                'pending_fulfillment_units' => number_format($pending['total_units'], 4, '.', ''),
                'pending_fulfillment_treatments_count' => $pending['treatments_count'],
            ],
            'sessions_by_status' => $sessionsByStatus,
            'pending_fulfillments' => $pending['list'],
            'by_professional' => $byProfessionalRows,
            'notes' => [
                'Sessions are filtered by scheduled_at within the period.',
                'pending_fulfillments lists open treatments with remaining sale units (limit '.self::PENDING_LIST_LIMIT.').',
                'by_professional uses completed appointments only (count + total_cost; no associated revenue in v1).',
            ],
        ];
    }

    /**
     * @return array{total_units: float, treatments_count: int, list: list<array<string, mixed>>}
     */
    private function pendingFulfillments(): array
    {
        $treatments = Treatment::query()
            ->where('status', Treatment::STATUS_OPEN)
            ->with(['client:id,name', 'sale:id'])
            ->orderBy('id')
            ->get();

        if ($treatments->isEmpty()) {
            return [
                'total_units' => 0.0,
                'treatments_count' => 0,
                'list' => [],
            ];
        }

        $treatmentIds = $treatments->pluck('id')->all();
        $saleIds = $treatments->pluck('sale_id')->filter()->all();

        $soldBySale = SaleItem::query()
            ->select('sale_id', DB::raw('COALESCE(SUM(quantity), 0) as sold_quantity'))
            ->whereIn('sale_id', $saleIds)
            ->groupBy('sale_id')
            ->pluck('sold_quantity', 'sale_id');

        $consumedByTreatment = AppointmentConsumption::query()
            ->select(
                'appointments.treatment_id',
                DB::raw('COALESCE(SUM(appointment_consumptions.quantity), 0) as consumed_quantity')
            )
            ->join('appointments', 'appointments.id', '=', 'appointment_consumptions.appointment_id')
            ->whereIn('appointments.treatment_id', $treatmentIds)
            ->where('appointments.status', Appointment::STATUS_COMPLETED)
            ->whereNotNull('appointment_consumptions.sale_item_id')
            ->groupBy('appointments.treatment_id')
            ->pluck('consumed_quantity', 'treatment_id');

        $list = [];
        $totalUnits = 0.0;

        foreach ($treatments as $treatment) {
            $sold = (float) ($soldBySale[$treatment->sale_id] ?? 0);
            $consumed = (float) ($consumedByTreatment[$treatment->id] ?? 0);
            $remaining = max(0, round($sold - $consumed, 4));

            if ($remaining <= 0) {
                continue;
            }

            $totalUnits += $remaining;
            $list[] = [
                'treatment_id' => $treatment->id,
                'sale_id' => $treatment->sale_id,
                'client_id' => $treatment->client_id,
                'client_name' => $treatment->client?->name,
                'remaining_units' => number_format($remaining, 4, '.', ''),
                'opened_at' => optional($treatment->created_at)?->toIso8601String(),
            ];
        }

        usort($list, fn (array $a, array $b): int => (float) $b['remaining_units'] <=> (float) $a['remaining_units']);

        return [
            'total_units' => round($totalUnits, 4),
            'treatments_count' => count($list),
            'list' => array_slice($list, 0, self::PENDING_LIST_LIMIT),
        ];
    }
}
