<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Sale;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AcquisitionMetricsService
{
    public const GROUP_BY_ORIGIN = 'origin';

    public const GROUP_BY_CAMPAIGN = 'campaign';

    /** @var list<string> */
    public const GROUP_BYS = [
        self::GROUP_BY_ORIGIN,
        self::GROUP_BY_CAMPAIGN,
    ];

    /**
     * Lifetime conversion: client created in [from, to] who has ≥1 confirmed sale
     * with sold_at >= client.created_at (sale may fall after the acquisition period).
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     group_by: string,
     *     conversion: string,
     *     conversion_definition: string,
     *     kpis: array<string, mixed>,
     *     breakdown: list<array<string, mixed>>
     * }
     */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to, string $groupBy = self::GROUP_BY_ORIGIN): array
    {
        if (! in_array($groupBy, self::GROUP_BYS, true)) {
            $groupBy = self::GROUP_BY_ORIGIN;
        }

        $fromStart = $from->startOfDay();
        $toEnd = $to->endOfDay();

        $breakdown = $groupBy === self::GROUP_BY_CAMPAIGN
            ? $this->breakdownByCampaign($fromStart, $toEnd)
            : $this->breakdownByOrigin($fromStart, $toEnd);

        return [
            'from' => $fromStart->toDateString(),
            'to' => $toEnd->toDateString(),
            'group_by' => $groupBy,
            'conversion' => 'lifetime',
            'conversion_definition' => 'Client created in the period with at least one confirmed sale where sold_at >= client.created_at. The sale may occur after the period (e.g. budget in month N, payment in month N+1).',
            'kpis' => $this->totalsFromBreakdown($breakdown),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function breakdownByOrigin(CarbonImmutable $fromStart, CarbonImmutable $toEnd): array
    {
        $rows = $this->baseCohortQuery($fromStart, $toEnd)
            ->leftJoin('client_origins', 'client_origins.id', '=', 'clients.client_origin_id')
            ->groupBy('clients.client_origin_id', 'client_origins.name')
            ->orderByRaw('client_origins.name is null')
            ->orderBy('client_origins.name')
            ->selectRaw('clients.client_origin_id as group_id')
            ->selectRaw('client_origins.name as group_name')
            ->selectRaw($this->aggregateSelect())
            ->get();

        return $rows->map(fn ($row): array => $this->mapRow(
            $row->group_id !== null ? (int) $row->group_id : null,
            $row->group_name !== null ? (string) $row->group_name : 'Sem origem',
            $row,
        ))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function breakdownByCampaign(CarbonImmutable $fromStart, CarbonImmutable $toEnd): array
    {
        $rows = $this->baseCohortQuery($fromStart, $toEnd)
            ->leftJoin('campaigns', 'campaigns.id', '=', 'clients.campaign_id')
            ->leftJoin('client_origins', 'client_origins.id', '=', 'campaigns.client_origin_id')
            ->groupBy('clients.campaign_id', 'campaigns.name', 'client_origins.name')
            ->orderByRaw('campaigns.name is null')
            ->orderBy('campaigns.name')
            ->selectRaw('clients.campaign_id as group_id')
            ->selectRaw('campaigns.name as group_name')
            ->selectRaw('client_origins.name as origin_name')
            ->selectRaw($this->aggregateSelect())
            ->get();

        return $rows->map(function ($row): array {
            $mapped = $this->mapRow(
                $row->group_id !== null ? (int) $row->group_id : null,
                $row->group_name !== null ? (string) $row->group_name : 'Sem campanha',
                $row,
            );
            $mapped['origin_label'] = $row->origin_name !== null ? (string) $row->origin_name : null;

            return $mapped;
        })->all();
    }

    private function baseCohortQuery(CarbonImmutable $fromStart, CarbonImmutable $toEnd)
    {
        $clinicId = CurrentClinic::id();

        $salesSub = DB::table('sales')
            ->join('clients as sale_clients', 'sale_clients.id', '=', 'sales.client_id')
            ->where('sales.status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sales.sold_at')
            ->whereColumn('sales.sold_at', '>=', 'sale_clients.created_at')
            ->when($clinicId !== null, fn ($query) => $query->where('sales.clinic_id', $clinicId))
            ->groupBy('sales.client_id')
            ->select('sales.client_id')
            ->selectRaw('COALESCE(SUM(sales.effective_amount), 0) as sales_revenue')
            ->selectRaw('COUNT(*) as sales_count');

        return Client::query()
            ->whereBetween('clients.created_at', [$fromStart, $toEnd])
            ->leftJoinSub($salesSub, 'sale_stats', 'sale_stats.client_id', '=', 'clients.id');
    }

    private function aggregateSelect(): string
    {
        return implode(', ', [
            'COUNT(clients.id) as new_clients',
            'COALESCE(SUM(clients.initial_consultation_amount), 0) as consultation_revenue',
            'COUNT(sale_stats.client_id) as converted_clients',
            'COALESCE(SUM(sale_stats.sales_revenue), 0) as sales_revenue',
            'COALESCE(SUM(sale_stats.sales_count), 0) as sales_count',
        ]);
    }

    /**
     * @param  object{
     *     new_clients: int|string,
     *     consultation_revenue: float|string,
     *     converted_clients: int|string,
     *     sales_revenue: float|string,
     *     sales_count: int|string
     * }  $row
     * @return array<string, mixed>
     */
    private function mapRow(?int $id, string $label, object $row): array
    {
        $newClients = (int) $row->new_clients;
        $converted = (int) $row->converted_clients;
        $consultationRevenue = (float) $row->consultation_revenue;
        $salesRevenue = (float) $row->sales_revenue;
        $salesCount = (int) $row->sales_count;

        return [
            'id' => $id,
            'key' => $id !== null ? (string) $id : 'unattributed',
            'label' => $label,
            'new_clients' => $newClients,
            'consultation_revenue' => $this->money($consultationRevenue),
            'converted_clients' => $converted,
            'conversion_rate' => $newClients > 0
                ? number_format(($converted / $newClients) * 100, 2, '.', '')
                : null,
            'sales_revenue' => $this->money($salesRevenue),
            'sales_count' => $salesCount,
            'avg_consultation_amount' => $newClients > 0
                ? $this->money($consultationRevenue / $newClients)
                : null,
            'sales_to_consultation_ratio' => $consultationRevenue > 0
                ? number_format($salesRevenue / $consultationRevenue, 2, '.', '')
                : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $breakdown
     * @return array<string, mixed>
     */
    private function totalsFromBreakdown(array $breakdown): array
    {
        $newClients = array_sum(array_column($breakdown, 'new_clients'));
        $converted = array_sum(array_column($breakdown, 'converted_clients'));
        $consultationRevenue = array_sum(array_map(
            fn (array $row): float => (float) $row['consultation_revenue'],
            $breakdown,
        ));
        $salesRevenue = array_sum(array_map(
            fn (array $row): float => (float) $row['sales_revenue'],
            $breakdown,
        ));
        $salesCount = array_sum(array_column($breakdown, 'sales_count'));

        return [
            'new_clients' => $newClients,
            'consultation_revenue' => $this->money($consultationRevenue),
            'converted_clients' => $converted,
            'conversion_rate' => $newClients > 0
                ? number_format(($converted / $newClients) * 100, 2, '.', '')
                : null,
            'sales_revenue' => $this->money($salesRevenue),
            'sales_count' => $salesCount,
            'avg_consultation_amount' => $newClients > 0
                ? $this->money($consultationRevenue / $newClients)
                : null,
            'sales_to_consultation_ratio' => $consultationRevenue > 0
                ? number_format($salesRevenue / $consultationRevenue, 2, '.', '')
                : null,
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
