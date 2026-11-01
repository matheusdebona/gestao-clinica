<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadDailyCheckin;
use App\Models\LeadStatus;
use App\Models\User;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Resumo do dia": the daily self-analysis of the lead follow-up routine.
 * Days are America/Sao_Paulo calendar days; timestamps are stored in UTC.
 */
class LeadDailyAnalysisService
{
    public const TZ = 'America/Sao_Paulo';

    public const QUEUE_LIMIT = 50;

    public function __construct(
        private readonly LeadStaleService $stale,
        private readonly LeadFunnelService $funnel,
    ) {}

    public static function today(): string
    {
        return CarbonImmutable::now(self::TZ)->toDateString();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} UTC bounds of the BRT days [from, to]
     */
    public static function bounds(string $from, string $to): array
    {
        return [
            CarbonImmutable::parse($from, self::TZ)->startOfDay()->utc(),
            CarbonImmutable::parse($to, self::TZ)->endOfDay()->utc(),
        ];
    }

    /**
     * @param  array{user_ids: list<int>, origin_ids: list<int>, status_ids: list<int>, stale_days: int|null}  $filters
     * @return array<string, mixed>
     */
    public function analyze(string $from, string $to, array $filters, bool $team, User $viewer): array
    {
        $clinicId = CurrentClinic::id();
        [$start, $end] = self::bounds($from, $to);
        $days = (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;

        $prevTo = CarbonImmutable::parse($from)->subDay()->toDateString();
        $prevFrom = CarbonImmutable::parse($from)->subDays($days)->toDateString();
        $avgFrom = CarbonImmutable::parse($from)->subDays(7)->toDateString();

        $kpis = $this->kpis($start, $end, $filters);
        $previous = $this->kpis(...[...self::bounds($prevFrom, $prevTo), $filters]);
        $week = $this->kpis(...[...self::bounds($avgFrom, $prevTo), $filters]);
        $average = array_map(fn (int $value): string => number_format($value / 7, 1, '.', ''), $week);

        $queue = $this->followUpQueue($filters, $clinicId);
        $funnel = $this->funnel->summarize($start, $end, $filters);

        $result = [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'today' => self::today(),
            'team' => $team,
            'kpis' => $kpis,
            'previous' => ['from' => $prevFrom, 'to' => $prevTo, 'kpis' => $previous],
            'average_7d' => ['from' => $avgFrom, 'to' => $prevTo, 'kpis' => $average],
            'registered_by_origin' => $this->registeredByOrigin($start, $end, $filters),
            'status_snapshot' => $this->statusSnapshot($filters),
            'funnel' => $funnel,
            'follow_up' => [
                'count' => $queue['count'],
                'leads' => $queue['leads'],
                // Patient returns due soon/overdue (only for users who see sales).
                'returns' => $viewer->can('sales.view')
                    ? app(PatientReturnService::class)->dueSummary(self::QUEUE_LIMIT, $filters['user_ids'] ?? [])
                    : null,
            ],
            'suggestions' => [],
        ];

        $result['suggestions'] = $this->suggestions($result, $filters, $viewer);

        if ($team) {
            $result['team_rows'] = $this->teamRows($start, $end, $from, $to, $filters, $clinicId);
        }

        return $result;
    }

    /**
     * @param  array{user_ids: list<int>, origin_ids: list<int>, status_ids: list<int>, stale_days: int|null}  $filters
     * @return array{registered: int, touched: int, approached: int, replied: int, booked: int, converted: int, cancelled: int}
     */
    public function kpis(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $users = $filters['user_ids'];

        $registered = $this->leadQuery($filters)
            ->whereBetween('leads.created_at', [$start, $end])
            ->when($users !== [], fn (Builder $q) => $q->whereIn('leads.created_by_user_id', $users))
            ->count();

        $milestone = fn (string $step): int => $this->leadQuery($filters)
            ->whereBetween("leads.{$step}_at", [$start, $end])
            ->when($users !== [], fn (Builder $q) => $q->whereIn("leads.{$step}_by_user_id", $users))
            ->count();

        return [
            'registered' => $registered,
            'touched' => $this->activityLeads($start, $end, $filters, LeadActivity::TOUCH_TYPES),
            'approached' => $milestone('approached'),
            'replied' => $milestone('replied'),
            'booked' => $milestone('booked'),
            'converted' => $this->activityLeads($start, $end, $filters, [LeadActivity::TYPE_CONVERTED]),
            'cancelled' => $this->activityLeads($start, $end, $filters, [LeadActivity::TYPE_CANCELLED]),
        ];
    }

    /**
     * Distinct leads with an activity of the given types by the users in the period.
     *
     * @param  list<string>  $types
     */
    private function activityLeads(CarbonImmutable $start, CarbonImmutable $end, array $filters, array $types): int
    {
        return (int) DB::table('lead_activities')
            ->join('leads', 'leads.id', '=', 'lead_activities.lead_id')
            ->where('leads.clinic_id', CurrentClinic::id())
            ->whereIn('lead_activities.type', $types)
            ->whereBetween('lead_activities.created_at', [$start, $end])
            ->when($filters['user_ids'] !== [], fn ($q) => $q->whereIn('lead_activities.user_id', $filters['user_ids']))
            ->when($filters['origin_ids'] !== [], fn ($q) => $q->whereIn('leads.client_origin_id', $filters['origin_ids']))
            ->when($filters['status_ids'] !== [], fn ($q) => $q->whereIn('leads.lead_status_id', $filters['status_ids']))
            ->distinct()
            ->count('lead_activities.lead_id');
    }

    private function leadQuery(array $filters): Builder
    {
        return Lead::query()
            ->when($filters['origin_ids'] !== [], fn (Builder $q) => $q->whereIn('leads.client_origin_id', $filters['origin_ids']))
            ->when($filters['status_ids'] !== [], fn (Builder $q) => $q->whereIn('leads.lead_status_id', $filters['status_ids']));
    }

    /** "My" open leads: registered by the selected users (all open leads when no user filter). */
    private function ownedOpenLeads(array $filters): Builder
    {
        return $this->leadQuery($filters)->open()
            ->when($filters['user_ids'] !== [], fn (Builder $q) => $q->whereIn('leads.created_by_user_id', $filters['user_ids']));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registeredByOrigin(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        return $this->leadQuery($filters)
            ->whereBetween('leads.created_at', [$start, $end])
            ->when($filters['user_ids'] !== [], fn (Builder $q) => $q->whereIn('leads.created_by_user_id', $filters['user_ids']))
            ->leftJoin('client_origins', 'client_origins.id', '=', 'leads.client_origin_id')
            ->groupBy('leads.client_origin_id', 'client_origins.name')
            ->selectRaw('leads.client_origin_id as id, client_origins.name as label, COUNT(*) as total')
            ->orderByDesc('total')
            ->toBase()
            ->get()
            ->map(fn ($row) => ['id' => $row->id !== null ? (int) $row->id : null, 'label' => $row->label ?? 'Sem origem', 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    /**
     * Where the open leads stopped (current snapshot by status, tier filter applied).
     *
     * @return list<array<string, mixed>>
     */
    private function statusSnapshot(array $filters): array
    {
        $query = $this->ownedOpenLeads($filters);
        if ($filters['stale_days'] !== null) {
            $query->stale(LeadStaleService::cutoff($filters['stale_days']));
        }

        $counts = $query->groupBy('leads.lead_status_id')
            ->selectRaw('leads.lead_status_id as id, COUNT(*) as total')
            ->toBase()
            ->get()
            ->keyBy('id');

        return LeadStatus::query()->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (LeadStatus $status) => $counts->has($status->id))
            ->map(fn (LeadStatus $status) => [
                'id' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
                'total' => (int) $counts->get($status->id)->total,
            ])
            ->values()
            ->all();
    }

    /**
     * Follow-up queue: open leads that reached a follow-up tier, or were never approached.
     *
     * @return array{count: int, leads: list<array<string, mixed>>}
     */
    public function followUpQueue(array $filters, ?int $clinicId): array
    {
        $tiers = $this->stale->tiers($clinicId);
        $min = $filters['stale_days'] ?? ($tiers->min('days') !== null ? (int) $tiers->min('days') : null);

        $query = $this->ownedOpenLeads($filters)->where(function (Builder $q) use ($min, $filters): void {
            if ($min !== null) {
                $q->whereIn('leads.id', Lead::query()->select('leads.id')->stale(LeadStaleService::cutoff($min)));
            }
            if ($filters['stale_days'] === null) {
                $q->orWhereNull('leads.approached_at');
            }
        });

        $count = (clone $query)->count();
        $now = CarbonImmutable::now();
        $leads = $query->withLastActivity()->with(['status', 'clientOrigin'])
            ->orderBy('activities_max_created_at')
            ->orderBy('leads.id')
            ->limit(self::QUEUE_LIMIT)
            ->get()
            ->map(function (Lead $lead) use ($tiers, $now): array {
                $last = $lead->lastActivityAt();
                $days = $last !== null ? (int) floor($last->diffInSeconds($now, true) / 86400) : 0;
                $tier = $tiers->sortByDesc('days')->first(fn ($tier) => $days >= $tier->days);

                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'client_id' => $lead->client_id,
                    'cancelled_at' => $lead->cancelled_at,
                    'lead_status_id' => $lead->lead_status_id,
                    'status' => $lead->status === null ? null : ['id' => $lead->status->id, 'name' => $lead->status->name, 'color' => $lead->status->color, 'system_key' => $lead->status->system_key],
                    'origin' => $lead->clientOrigin?->name,
                    'last_activity_at' => $last?->toISOString(),
                    'days_without_contact' => $days,
                    'tier' => $tier === null ? null : ['days' => $tier->days, 'label' => $tier->displayLabel(), 'color' => $tier->color],
                    'approached_at' => $lead->approached_at,
                    'replied_at' => $lead->replied_at,
                    'reason' => $lead->approached_at === null ? 'not_approached' : 'stale',
                ];
            })
            ->values()
            ->all();

        return ['count' => $count, 'leads' => $leads];
    }

    /**
     * Rule-based pt-BR suggestions.
     *
     * @return list<array{kind: string, text: string}>
     */
    private function suggestions(array $result, array $filters, User $viewer): array
    {
        $out = [];
        $self = $filters['user_ids'] === [$viewer->id];
        $you = $self ? 'Você' : 'A equipe selecionada';

        // Leads stuck in a status for more than 7 days.
        $stuck = $this->ownedOpenLeads($filters)->stale(LeadStaleService::cutoff(7))
            ->groupBy('leads.lead_status_id')
            ->selectRaw('leads.lead_status_id as id, COUNT(*) as total')
            ->toBase()->get();
        $names = LeadStatus::query()->pluck('name', 'id');
        foreach ($stuck->sortByDesc('total') as $row) {
            $n = (int) $row->total;
            $status = $names[$row->id] ?? 'sem status';
            $out[] = ['kind' => 'warning', 'text' => ($n === 1 ? '1 lead' : "{$n} leads")." em {$status} há mais de 7 dias sem contato."];
        }

        // Origins with leads this week but no booking.
        [$weekStart, $weekEnd] = self::bounds(CarbonImmutable::parse(self::today())->subDays(6)->toDateString(), self::today());
        $weekly = $this->leadQuery($filters)
            ->whereBetween('leads.created_at', [$weekStart, $weekEnd])
            ->when($filters['user_ids'] !== [], fn (Builder $q) => $q->whereIn('leads.created_by_user_id', $filters['user_ids']))
            ->leftJoin('client_origins', 'client_origins.id', '=', 'leads.client_origin_id')
            ->groupBy('client_origins.name')
            ->selectRaw('client_origins.name as label, COUNT(*) as total, COUNT(leads.booked_at) as booked')
            ->toBase()->get();
        foreach ($weekly as $row) {
            if ((int) $row->total >= 5 && (int) $row->booked === 0) {
                $out[] = ['kind' => 'warning', 'text' => 'Origem '.($row->label ?? 'sem origem').": 0 de {$row->total} agendaram nos últimos 7 dias."];
            }
        }

        // Low reply rate.
        $k = $result['kpis'];
        if ($k['approached'] >= 5 && $k['replied'] / $k['approached'] < 0.2) {
            $out[] = ['kind' => 'info', 'text' => "Poucas respostas: {$k['replied']} de {$k['approached']} abordados responderam. Vale revisar a mensagem do WhatsApp."];
        }

        // Untouched queue.
        $pending = $result['follow_up']['count'];
        if ($pending > 0) {
            $out[] = ['kind' => 'warning', 'text' => $self
                ? "Você tem {$pending} ".($pending === 1 ? 'lead esperando' : 'leads esperando').' acompanhamento.'
                : "{$pending} ".($pending === 1 ? 'lead esperando' : 'leads esperando').' acompanhamento.'];
        }

        if ($k['touched'] === 0 && $result['to'] === self::today()) {
            $out[] = ['kind' => 'info', 'text' => "{$you} ainda não tocou em nenhum lead hoje."];
        }

        if ($self && $result['to'] === self::today()
            && ! LeadDailyCheckin::query()->where('user_id', $viewer->id)->whereDate('day', self::today())->exists()) {
            $out[] = ['kind' => 'info', 'text' => 'Registre seu check-in do dia: o que funcionou e o que mudar amanhã.'];
        }

        if ($out === []) {
            $out[] = ['kind' => 'success', 'text' => 'Tudo em dia. Bom trabalho!'];
        }

        return $out;
    }

    /**
     * Team comparison: one row per user (selected users or everyone who works leads).
     *
     * @return list<array<string, mixed>>
     */
    private function teamRows(CarbonImmutable $start, CarbonImmutable $end, string $from, string $to, array $filters, ?int $clinicId): array
    {
        $users = $this->teamUsers($clinicId, $filters['user_ids']);
        $checkins = LeadDailyCheckin::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->whereBetween('day', [$from, $to])
            ->orderBy('day')
            ->get()
            ->groupBy('user_id');

        return $users->map(function (User $user) use ($start, $end, $filters, $clinicId, $checkins, $from, $to): array {
            $scoped = [...$filters, 'user_ids' => [$user->id]];
            $userCheckins = $checkins->get($user->id, collect());
            $last = $userCheckins->last();

            return [
                'user' => ['id' => $user->id, 'name' => $user->name],
                ...$this->kpis($start, $end, $scoped),
                'pending_follow_ups' => $this->followUpQueue($scoped, $clinicId)['count'],
                'checkins' => $userCheckins->count(),
                'checkin_done' => $from === $to ? $userCheckins->isNotEmpty() : null,
                'checkin' => $last === null ? null : [
                    'day' => $last->day->toDateString(),
                    'worked' => $last->worked,
                    'change' => $last->change,
                    'notes' => $last->notes,
                ],
            ];
        })->values()->all();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    public function teamUsers(?int $clinicId, array $ids = []): Collection
    {
        return User::query()->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->can('leads.view'))
            ->values();
    }
}
