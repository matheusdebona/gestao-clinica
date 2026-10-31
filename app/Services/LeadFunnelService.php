<?php

namespace App\Services;

use App\Models\Lead;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fixed funnel (independent of the configurable statuses): Abordados → Responderam → Agendaram.
 * Cohort = leads first approached in the period (attributed to who approached them).
 */
class LeadFunnelService
{
    /**
     * @param  array{user_ids?: list<int>, origin_ids?: list<int>, status_ids?: list<int>}  $filters
     * @return array{total: array<string, mixed>, by_origin: list<array<string, mixed>>, by_user: list<array<string, mixed>>}
     */
    public function summarize(CarbonInterface $from, CarbonInterface $to, array $filters = []): array
    {
        $base = fn (): Builder => $this->cohort($from, $to, $filters);

        $total = $base()->selectRaw($this->countsSql())->toBase()->first();

        $byOrigin = $base()
            ->leftJoin('client_origins', 'client_origins.id', '=', 'leads.client_origin_id')
            ->groupBy('leads.client_origin_id', 'client_origins.name')
            ->selectRaw('leads.client_origin_id as id, client_origins.name as label, '.$this->countsSql())
            ->orderByDesc('approached')
            ->toBase()
            ->get();

        $byUser = $base()
            ->leftJoin('users', 'users.id', '=', 'leads.approached_by_user_id')
            ->groupBy('leads.approached_by_user_id', 'users.name')
            ->selectRaw('leads.approached_by_user_id as id, users.name as label, '.$this->countsSql())
            ->orderByDesc('approached')
            ->toBase()
            ->get();

        return [
            'total' => $this->row(null, 'Total', $total),
            'by_origin' => $byOrigin->map(fn ($row) => $this->row($row->id, $row->label ?? 'Sem origem', $row))->values()->all(),
            'by_user' => $byUser->map(fn ($row) => $this->row($row->id, $row->label ?? 'Sem usuário', $row))->values()->all(),
        ];
    }

    /**
     * @param  array{user_ids?: list<int>, origin_ids?: list<int>, status_ids?: list<int>}  $filters
     */
    public function cohort(CarbonInterface $from, CarbonInterface $to, array $filters = []): Builder
    {
        $query = Lead::query()->whereBetween('leads.approached_at', [$from, $to]);

        if (! empty($filters['user_ids'])) {
            $query->whereIn('leads.approached_by_user_id', $filters['user_ids']);
        }
        if (! empty($filters['origin_ids'])) {
            $query->whereIn('leads.client_origin_id', $filters['origin_ids']);
        }
        if (! empty($filters['status_ids'])) {
            $query->whereIn('leads.lead_status_id', $filters['status_ids']);
        }

        return $query;
    }

    private function countsSql(): string
    {
        return 'COUNT(*) as approached, COUNT(leads.replied_at) as replied, COUNT(leads.booked_at) as booked';
    }

    /**
     * @return array<string, mixed>
     */
    private function row(mixed $id, string $label, ?object $row): array
    {
        $approached = (int) ($row->approached ?? 0);
        $replied = (int) ($row->replied ?? 0);
        $booked = (int) ($row->booked ?? 0);

        return [
            'id' => $id !== null ? (int) $id : null,
            'key' => $id !== null ? (string) $id : 'none',
            'label' => $label,
            'approached' => $approached,
            'replied' => $replied,
            'booked' => $booked,
            'reply_rate' => $this->rate($replied, $approached),
            'booking_rate' => $this->rate($booked, $replied),
            'total_rate' => $this->rate($booked, $approached),
        ];
    }

    private function rate(int $part, int $whole): ?string
    {
        return $whole > 0 ? number_format($part / $whole * 100, 2, '.', '') : null;
    }
}
