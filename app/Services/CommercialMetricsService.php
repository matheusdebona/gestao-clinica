<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommercialMetricsService
{
    public const GRANULARITY_DAY = 'day';

    public const GRANULARITY_WEEK = 'week';

    public const GRANULARITY_MONTH = 'month';

    /** @var list<string> */
    public const GRANULARITIES = [
        self::GRANULARITY_DAY,
        self::GRANULARITY_WEEK,
        self::GRANULARITY_MONTH,
    ];

    /**
     * Resolve series bucket size for a date range.
     * ≤62 days → day; ≤366 days → week; else → month.
     */
    public function resolveGranularity(CarbonImmutable $from, CarbonImmutable $to, ?string $requested = null): string
    {
        if ($requested !== null && in_array($requested, self::GRANULARITIES, true)) {
            return $requested;
        }

        $days = $from->diffInDays($to) + 1;

        if ($days <= 62) {
            return self::GRANULARITY_DAY;
        }

        if ($days <= 366) {
            return self::GRANULARITY_WEEK;
        }

        return self::GRANULARITY_MONTH;
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     date_field: string,
     *     granularity: string,
     *     kpis: array<string, mixed>,
     *     payment_mix: list<array<string, mixed>>,
     *     budget_funnel: array<string, mixed>,
     *     series: list<array{period: string, revenue: string, sales_count: int}>
     * }
     */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to, ?string $granularity = null): array
    {
        $fromStart = $from->startOfDay();
        $toEnd = $to->endOfDay();
        $resolvedGranularity = $this->resolveGranularity($fromStart, $toEnd, $granularity);

        $confirmedSales = Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$fromStart, $toEnd]);

        $revenue = (float) (clone $confirmedSales)->sum('effective_amount');
        $salesCount = (int) (clone $confirmedSales)->count();
        $ticketAvg = $salesCount > 0 ? $revenue / $salesCount : 0.0;

        $itemTotals = SaleItem::query()
            ->whereHas('sale', function ($query) use ($fromStart, $toEnd): void {
                $query->where('status', Sale::STATUS_CONFIRMED)
                    ->whereNotNull('sold_at')
                    ->whereBetween('sold_at', [$fromStart, $toEnd]);
            })
            ->selectRaw('COALESCE(SUM(list_line_total), 0) as list_total, COALESCE(SUM(line_total), 0) as offered_total')
            ->first();

        $listTotal = (float) ($itemTotals->list_total ?? 0);
        $offeredTotal = (float) ($itemTotals->offered_total ?? 0);
        $avgDiscountPercent = $listTotal > 0
            ? (1 - ($offeredTotal / $listTotal)) * 100
            : null;

        return [
            'from' => $fromStart->toDateString(),
            'to' => $toEnd->toDateString(),
            'date_field' => 'sold_at',
            'granularity' => $resolvedGranularity,
            'kpis' => [
                'revenue' => $this->money($revenue),
                'sales_count' => $salesCount,
                'ticket_avg' => $this->money($ticketAvg),
                'avg_discount_percent' => $avgDiscountPercent === null
                    ? null
                    : number_format($avgDiscountPercent, 2, '.', ''),
                'list_total' => $this->money($listTotal),
                'offered_total' => $this->money($offeredTotal),
            ],
            'payment_mix' => $this->paymentMix($fromStart, $toEnd),
            'budget_funnel' => $this->budgetFunnel($fromStart, $toEnd),
            'series' => $this->revenueSeries($fromStart, $toEnd, $resolvedGranularity),
        ];
    }

    /**
     * @return list<array{payment_method_id: int, name: string, kind: string, amount: string, payments_count: int}>
     */
    private function paymentMix(CarbonImmutable $fromStart, CarbonImmutable $toEnd): array
    {
        $clinicId = CurrentClinic::id();

        $query = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sales.sold_at')
            ->whereBetween('sales.sold_at', [$fromStart, $toEnd]);

        if ($clinicId !== null) {
            $query->where('sales.clinic_id', $clinicId);
        }

        $rows = $query
            ->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.kind')
            ->orderBy('payment_methods.name')
            ->selectRaw('payment_methods.id as payment_method_id')
            ->selectRaw('payment_methods.name as name')
            ->selectRaw('payment_methods.kind as kind')
            ->selectRaw('COALESCE(SUM(sale_payments.amount), 0) as amount')
            ->selectRaw('COUNT(sale_payments.id) as payments_count')
            ->get();

        return $rows->map(fn ($row): array => [
            'payment_method_id' => (int) $row->payment_method_id,
            'name' => (string) $row->name,
            'kind' => (string) $row->kind,
            'amount' => $this->money((float) $row->amount),
            'payments_count' => (int) $row->payments_count,
        ])->all();
    }

    /**
     * @return array{
     *     by_status: array<string, int>,
     *     sent_in_period: int,
     *     accepted_in_period: int,
     *     rejected_in_period: int,
     *     acceptance_rate: string|null
     * }
     */
    private function budgetFunnel(CarbonImmutable $fromStart, CarbonImmutable $toEnd): array
    {
        $byStatus = Budget::query()
            ->whereBetween('created_at', [$fromStart, $toEnd])
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();

        foreach (Budget::STATUSES as $status) {
            $byStatus[$status] = $byStatus[$status] ?? 0;
        }

        $sentInPeriod = (int) Budget::query()
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$fromStart, $toEnd])
            ->count();

        $acceptedInPeriod = (int) Budget::query()
            ->whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$fromStart, $toEnd])
            ->count();

        $rejectedInPeriod = (int) Budget::query()
            ->whereNotNull('rejected_at')
            ->whereBetween('rejected_at', [$fromStart, $toEnd])
            ->count();

        $acceptanceRate = $sentInPeriod > 0
            ? number_format(($acceptedInPeriod / $sentInPeriod) * 100, 2, '.', '')
            : null;

        return [
            'by_status' => $byStatus,
            'sent_in_period' => $sentInPeriod,
            'accepted_in_period' => $acceptedInPeriod,
            'rejected_in_period' => $rejectedInPeriod,
            'acceptance_rate' => $acceptanceRate,
        ];
    }

    /**
     * @return list<array{period: string, revenue: string, sales_count: int}>
     */
    private function revenueSeries(CarbonImmutable $fromStart, CarbonImmutable $toEnd, string $granularity): array
    {
        $bucketExpr = $this->periodExpression('sold_at', $granularity);

        $rows = Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$fromStart, $toEnd])
            ->selectRaw("{$bucketExpr} as period")
            ->selectRaw('COALESCE(SUM(effective_amount), 0) as revenue')
            ->selectRaw('COUNT(*) as sales_count')
            ->groupByRaw($bucketExpr)
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        return $this->fillSeriesBuckets($fromStart, $toEnd, $granularity, $rows);
    }

    /**
     * @param  Collection<string, object>  $rows
     * @return list<array{period: string, revenue: string, sales_count: int}>
     */
    private function fillSeriesBuckets(
        CarbonImmutable $fromStart,
        CarbonImmutable $toEnd,
        string $granularity,
        Collection $rows,
    ): array {
        $series = [];
        $cursor = $this->bucketStart($fromStart, $granularity);
        $last = $this->bucketStart($toEnd, $granularity);

        while ($cursor->lte($last)) {
            $key = $this->bucketKey($cursor, $granularity);
            $row = $rows->get($key);
            $series[] = [
                'period' => $key,
                'revenue' => $this->money((float) ($row->revenue ?? 0)),
                'sales_count' => (int) ($row->sales_count ?? 0),
            ];
            $cursor = $this->nextBucket($cursor, $granularity);
        }

        return $series;
    }

    private function periodExpression(string $column, string $granularity): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($granularity) {
            self::GRANULARITY_DAY => match ($driver) {
                'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
                default => "strftime('%Y-%m-%d', {$column})",
            },
            self::GRANULARITY_WEEK => match ($driver) {
                // date_trunc('week') is Monday in PostgreSQL.
                'pgsql' => "to_char(date_trunc('week', {$column}), 'YYYY-MM-DD')",
                // SQLite %w: 0=Sunday … 6=Saturday → shift to Monday-start.
                default => "strftime('%Y-%m-%d', date({$column}, '-' || ((strftime('%w', {$column}) + 6) % 7) || ' days'))",
            },
            self::GRANULARITY_MONTH => match ($driver) {
                'pgsql' => "to_char(date_trunc('month', {$column}), 'YYYY-MM-DD')",
                default => "strftime('%Y-%m-01', {$column})",
            },
            default => match ($driver) {
                'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
                default => "strftime('%Y-%m-%d', {$column})",
            },
        };
    }

    private function bucketStart(CarbonImmutable $date, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            self::GRANULARITY_WEEK => $date->startOfWeek(CarbonImmutable::MONDAY),
            self::GRANULARITY_MONTH => $date->startOfMonth(),
            default => $date->startOfDay(),
        };
    }

    private function bucketKey(CarbonImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            self::GRANULARITY_WEEK => $date->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
            self::GRANULARITY_MONTH => $date->startOfMonth()->toDateString(),
            default => $date->toDateString(),
        };
    }

    private function nextBucket(CarbonImmutable $date, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            self::GRANULARITY_WEEK => $date->addWeek(),
            self::GRANULARITY_MONTH => $date->addMonth(),
            default => $date->addDay(),
        };
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
