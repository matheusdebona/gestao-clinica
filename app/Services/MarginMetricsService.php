<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentConsumption;
use App\Models\Sale;
use App\Models\Treatment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class MarginMetricsService
{
    public const MODE_PERIOD = 'period';

    public const MODE_COHORT_SALE = 'cohort_sale';

    /** @var list<string> */
    public const MODES = [
        self::MODE_PERIOD,
        self::MODE_COHORT_SALE,
    ];

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     mode: string,
     *     kpis: array<string, mixed>,
     *     notes: string
     * }
     */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to, string $mode = self::MODE_PERIOD): array
    {
        if (! in_array($mode, self::MODES, true)) {
            $mode = self::MODE_PERIOD;
        }

        $fromStart = $from->startOfDay();
        $toEnd = $to->endOfDay();

        $kpis = $mode === self::MODE_COHORT_SALE
            ? $this->cohortSale($fromStart, $toEnd)
            : $this->period($fromStart, $toEnd);

        return [
            'from' => $fromStart->toDateString(),
            'to' => $toEnd->toDateString(),
            'mode' => $mode,
            'kpis' => $kpis,
            'notes' => $this->notesFor($mode),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function period(CarbonImmutable $fromStart, CarbonImmutable $toEnd): array
    {
        $saleRevenue = (float) Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$fromStart, $toEnd])
            ->sum('effective_amount');

        $completedInPeriod = Appointment::query()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$fromStart, $toEnd]);

        $clinicalCost = (float) (clone $completedInPeriod)->sum('total_cost');
        $extrasRevenue = (float) (clone $completedInPeriod)->sum('total_charged_on_appointment');
        $courtesyCost = $this->courtesyCostForAppointments(clone $completedInPeriod);

        return $this->buildKpis(
            saleRevenue: $saleRevenue,
            extrasRevenue: $extrasRevenue,
            clinicalCost: $clinicalCost,
            courtesyCost: $courtesyCost,
            pendingFulfillmentCount: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cohortSale(CarbonImmutable $fromStart, CarbonImmutable $toEnd): array
    {
        $cohortSaleIds = Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$fromStart, $toEnd])
            ->pluck('id');

        $saleRevenue = (float) Sale::query()
            ->whereIn('id', $cohortSaleIds)
            ->sum('effective_amount');

        $treatmentIds = Treatment::query()
            ->whereIn('sale_id', $cohortSaleIds)
            ->pluck('id');

        $completedForCohort = Appointment::query()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereIn('treatment_id', $treatmentIds);

        $clinicalCost = (float) (clone $completedForCohort)->sum('total_cost');
        $extrasRevenue = (float) (clone $completedForCohort)->sum('total_charged_on_appointment');
        $courtesyCost = $this->courtesyCostForAppointments(clone $completedForCohort);

        $pendingFulfillmentCount = (int) Treatment::query()
            ->whereIn('sale_id', $cohortSaleIds)
            ->where('status', Treatment::STATUS_OPEN)
            ->count();

        return $this->buildKpis(
            saleRevenue: $saleRevenue,
            extrasRevenue: $extrasRevenue,
            clinicalCost: $clinicalCost,
            courtesyCost: $courtesyCost,
            pendingFulfillmentCount: $pendingFulfillmentCount,
        );
    }

    private function courtesyCostForAppointments(Builder $appointmentsQuery): float
    {
        $appointmentIds = $appointmentsQuery->pluck('id');

        if ($appointmentIds->isEmpty()) {
            return 0.0;
        }

        return (float) AppointmentConsumption::query()
            ->whereIn('appointment_id', $appointmentIds)
            ->where('is_complimentary', true)
            ->sum('line_cost');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKpis(
        float $saleRevenue,
        float $extrasRevenue,
        float $clinicalCost,
        float $courtesyCost,
        ?int $pendingFulfillmentCount,
    ): array {
        $revenue = $saleRevenue + $extrasRevenue;
        $grossMargin = $revenue - $clinicalCost;

        return [
            'sale_revenue' => $this->money($saleRevenue),
            'extras_revenue' => $this->money($extrasRevenue),
            'revenue' => $this->money($revenue),
            'clinical_cost' => $this->money($clinicalCost),
            'courtesy_cost' => $this->money($courtesyCost),
            'gross_margin' => $this->money($grossMargin),
            'margin_percent' => $revenue > 0
                ? number_format(($grossMargin / $revenue) * 100, 2, '.', '')
                : null,
            'pending_fulfillment_count' => $pendingFulfillmentCount,
        ];
    }

    private function notesFor(string $mode): string
    {
        $base = 'Gross clinical margin excludes taxes, card fees, and fixed costs.';

        if ($mode === self::MODE_COHORT_SALE) {
            return $base.' Mode cohort_sale: sales confirmed in the period; costs from completed appointments on those treatments (any date). pending_fulfillment_count is open treatments in the cohort.';
        }

        return $base.' Mode period: sale revenue by sold_at in range; clinical cost and extras by appointment finished_at in range (cash/ops view).';
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
