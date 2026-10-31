<?php

namespace App\Services;

use App\Models\Lead;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LeadMetricsService
{
    /**
     * Cohort metrics: leads created in [from, to] (clinic-scoped by the Lead global scope).
     * Totals include cancelled leads. A lead counts as converted when it became a patient
     * (converted_at set), even if the conversion happened after the period.
     *
     * @return array<string, mixed>
     */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $fromStart = $from->startOfDay();
        $toEnd = $to->endOfDay();

        $totals = $this->cohort($fromStart, $toEnd)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(leads.converted_at) as converted')
            ->selectRaw('COUNT(leads.cancelled_at) as cancelled')
            ->selectRaw($this->avgSecondsExpression().' as avg_seconds')
            ->toBase()
            ->first();

        $total = (int) ($totals->total ?? 0);
        $converted = (int) ($totals->converted ?? 0);
        $cancelled = (int) ($totals->cancelled ?? 0);
        $avgSeconds = $totals->avg_seconds !== null ? (float) $totals->avg_seconds : null;

        return [
            'from' => $fromStart->toDateString(),
            'to' => $toEnd->toDateString(),
            'conversion_definition' => 'Converted / all leads created in the period (cancelled included). Converted = became a patient, even after the period.',
            'kpis' => [
                'total' => $total,
                'converted' => $converted,
                'conversion_rate' => $this->rate($converted, $total),
                'cancelled' => $cancelled,
                'cancelled_rate' => $this->rate($cancelled, $total),
                // Snapshot (today), not tied to the period.
                'stale' => app(LeadStaleService::class)->count(CurrentClinic::id()),
                'avg_days_to_conversion' => $avgSeconds !== null
                    ? number_format(max(0, $avgSeconds) / 86400, 1, '.', '')
                    : null,
            ],
            'stale_tiers' => app(LeadStaleService::class)->tierCounts(CurrentClinic::id()),
            'funnel' => app(LeadFunnelService::class)->summarize($fromStart, $toEnd),
            'by_origin' => $this->byOrigin($fromStart, $toEnd),
            'by_status' => $this->byStatus($fromStart, $toEnd),
            'by_creator' => $this->byCreator($fromStart, $toEnd),
        ];
    }

    private function cohort(CarbonImmutable $fromStart, CarbonImmutable $toEnd): Builder
    {
        return Lead::query()->whereBetween('leads.created_at', [$fromStart, $toEnd]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byOrigin(CarbonImmutable $fromStart, CarbonImmutable $toEnd): array
    {
        return $this->cohort($fromStart, $toEnd)
            ->leftJoin('client_origins', 'client_origins.id', '=', 'leads.client_origin_id')
            ->groupBy('leads.client_origin_id', 'client_origins.name')
            ->select('leads.client_origin_id as group_id', 'client_origins.name as group_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(leads.converted_at) as converted')
            ->selectRaw('COUNT(leads.cancelled_at) as cancelled')
            ->orderByDesc('total')
            ->orderBy('client_origins.name')
            ->toBase()
            ->get()
            ->map(fn ($row): array => $this->row(
                $row->group_id !== null ? (int) $row->group_id : null,
                $row->group_name ?? 'Sem origem',
                (int) $row->total,
                (int) $row->converted,
                (int) $row->cancelled,
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byStatus(CarbonImmutable $fromStart, CarbonImmutable $toEnd): array
    {
        return $this->cohort($fromStart, $toEnd)
            ->join('lead_statuses', 'lead_statuses.id', '=', 'leads.lead_status_id')
            ->groupBy('leads.lead_status_id', 'lead_statuses.name', 'lead_statuses.color', 'lead_statuses.sort_order', 'lead_statuses.system_key')
            ->select(
                'leads.lead_status_id as group_id',
                'lead_statuses.name as group_name',
                'lead_statuses.color',
                'lead_statuses.system_key',
            )
            ->selectRaw('COUNT(*) as total')
            ->orderBy('lead_statuses.sort_order')
            ->orderBy('lead_statuses.name')
            ->toBase()
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->group_id,
                'key' => (string) $row->group_id,
                'label' => (string) $row->group_name,
                'color' => (string) $row->color,
                'system_key' => $row->system_key,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Ranking of who registered the leads, with how many of them became patients.
     *
     * @return list<array<string, mixed>>
     */
    private function byCreator(CarbonImmutable $fromStart, CarbonImmutable $toEnd): array
    {
        return $this->cohort($fromStart, $toEnd)
            ->leftJoin('users', 'users.id', '=', 'leads.created_by_user_id')
            ->groupBy('leads.created_by_user_id', 'users.name')
            ->select('leads.created_by_user_id as group_id', 'users.name as group_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(leads.converted_at) as converted')
            ->selectRaw('COUNT(leads.cancelled_at) as cancelled')
            ->orderByDesc('converted')
            ->orderByDesc('total')
            ->orderBy('users.name')
            ->toBase()
            ->get()
            ->map(fn ($row): array => $this->row(
                $row->group_id !== null ? (int) $row->group_id : null,
                $row->group_name ?? 'Sem usuário',
                (int) $row->total,
                (int) $row->converted,
                (int) $row->cancelled,
            ))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(?int $id, string $label, int $total, int $converted, int $cancelled): array
    {
        return [
            'id' => $id,
            'key' => $id !== null ? (string) $id : 'unattributed',
            'label' => $label,
            'total' => $total,
            'converted' => $converted,
            'conversion_rate' => $this->rate($converted, $total),
            'cancelled' => $cancelled,
        ];
    }

    private function rate(int $converted, int $total): ?string
    {
        return $total > 0 ? number_format(($converted / $total) * 100, 2, '.', '') : null;
    }

    private function avgSecondsExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (leads.converted_at - leads.created_at)))',
            'mysql', 'mariadb' => 'AVG(TIMESTAMPDIFF(SECOND, leads.created_at, leads.converted_at))',
            default => 'AVG((julianday(leads.converted_at) - julianday(leads.created_at)) * 86400)',
        };
    }
}
