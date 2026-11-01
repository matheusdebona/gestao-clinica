<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * End-to-end sales funnel (Métricas → "Funil de vendas").
 *
 * Cohort: leads created in [from, to] (cancelled included). Each lead walks the stages
 * lead criado → abordado → respondeu → agendou → compareceu → orçamento criado → venda,
 * counted once per lead, lifetime (later stages may happen after the period):
 *  - abordado / respondeu: the lead's funnel milestones (approached_at / replied_at);
 *  - agendou: booked_at milestone, lead converted into a patient, or a non-cancelled
 *    appointment for the converted patient;
 *  - compareceu: an appointment of the patient in progress or completed (the agenda has no
 *    separate "attended" status: started = the patient showed up);
 *  - orçamento criado: any budget of the patient;
 *  - venda: a confirmed sale or an accepted budget of the patient;
 *  - receita: sum of the confirmed sales' effective amount (sold_at ≥ lead creation).
 * Appointments, budgets and sales only count from the lead's creation on.
 *
 * Attribution: origem = the lead's origem (the patient inherits it); colaborador = who
 * registered the lead (leads.created_by_user_id), so every step stays with one owner.
 * Patients registered directly (no lead) in the period form a separate "Sem lead" row so
 * sales and revenue add up; they follow the patient's own origem and have no collaborator.
 * Everything is clinic-scoped (Lead/Client global scopes + same-clinic subqueries).
 */
class SalesFunnelMetricsService
{
    public const STAGES = [
        'leads' => 'Lead criado',
        'approached' => 'Abordado',
        'replied' => 'Respondeu',
        'booked' => 'Agendou',
        'attended' => 'Compareceu',
        'budgeted' => 'Orçamento criado',
        'sold' => 'Venda',
    ];

    private const NO_LEAD_KEY = 'no_lead';

    /**
     * @param  array{origin_id?: int|null, user_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to, array $filters = []): array
    {
        $fromStart = $from->startOfDay();
        $toEnd = $to->endOfDay();
        $originId = $filters['origin_id'] ?? null;
        $userId = $filters['user_id'] ?? null;

        $leads = $this->leadRows($fromStart, $toEnd, $originId, $userId);
        // Direct patients have no collaborator: the collaborator filter leaves them out.
        $direct = $userId === null ? $this->directPatientRows($fromStart, $toEnd, $originId) : collect();

        $leadTotals = $this->aggregate($leads);
        $directTotals = $this->aggregate($direct);

        return [
            'from' => $fromStart->toDateString(),
            'to' => $toEnd->toDateString(),
            'filters' => ['origin_id' => $originId, 'user_id' => $userId],
            'definition' => 'Leads criados no período; cada etapa conta uma vez por lead, mesmo se acontecer depois do período. Origem e colaborador são os do lead (quem cadastrou). Pacientes cadastrados sem lead aparecem em "Sem lead".',
            'stages' => $this->stages($leadTotals),
            'kpis' => [
                'leads' => $leadTotals['leads'],
                'sold' => $leadTotals['sold'],
                'conversion_rate' => $this->rate($leadTotals['sold'], $leadTotals['leads']),
                'revenue' => $this->money($leadTotals['revenue']),
                'sales_count' => $leadTotals['sales_count'],
                'ticket_avg' => $this->ticket($leadTotals['revenue'], $leadTotals['sales_count']),
            ],
            'no_lead' => $this->row(self::NO_LEAD_KEY, null, 'Sem lead', $directTotals, false),
            'totals' => $this->row('total', null, 'Total', $this->sum($leadTotals, $directTotals), false),
            'by_origin' => $this->breakdown($leads, 'origin_id', 'origin_name', 'Sem origem', $directTotals),
            'by_user' => $this->breakdown($leads, 'user_id', 'user_name', 'Sem usuário', $directTotals),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function leadRows(CarbonImmutable $fromStart, CarbonImmutable $toEnd, ?int $originId, ?int $userId): Collection
    {
        $activeAppointment = fn ($q) => $q->where('appointments.status', '!=', Appointment::STATUS_CANCELLED);
        $attended = fn ($q) => $q->whereIn('appointments.status', [Appointment::STATUS_IN_PROGRESS, Appointment::STATUS_COMPLETED]);

        $query = Lead::query()
            ->whereBetween('leads.created_at', [$fromStart, $toEnd])
            ->leftJoin('client_origins', 'client_origins.id', '=', 'leads.client_origin_id')
            ->leftJoin('users', 'users.id', '=', 'leads.created_by_user_id')
            ->select([
                'leads.id',
                'leads.client_origin_id as origin_id',
                'client_origins.name as origin_name',
                'leads.created_by_user_id as user_id',
                'users.name as user_name',
            ])
            ->selectRaw('CASE WHEN leads.approached_at IS NOT NULL THEN 1 ELSE 0 END as approached')
            ->selectRaw('CASE WHEN leads.replied_at IS NOT NULL THEN 1 ELSE 0 END as replied')
            ->selectRaw('CASE WHEN leads.booked_at IS NOT NULL OR leads.converted_at IS NOT NULL THEN 1 ELSE 0 END as milestone_booked')
            ->selectSub($this->exists(Appointment::class, 'leads', $activeAppointment), 'has_appointment')
            ->selectSub($this->exists(Appointment::class, 'leads', $attended), 'attended')
            ->selectSub($this->exists(Budget::class, 'leads'), 'budgeted')
            ->selectSub($this->exists(Budget::class, 'leads', fn ($q) => $q->where('budgets.status', Budget::STATUS_ACCEPTED)), 'budget_accepted')
            ->selectSub($this->salesAggregate('leads', 'COUNT(*)'), 'sales_count')
            ->selectSub($this->salesAggregate('leads', 'COALESCE(SUM(sales.effective_amount), 0)'), 'revenue');

        if ($originId !== null) {
            $query->where('leads.client_origin_id', $originId);
        }
        if ($userId !== null) {
            $query->where('leads.created_by_user_id', $userId);
        }

        return $query->toBase()->get()->map(function ($row) {
            $row->is_lead = 1;
            $row->booked = ((int) $row->milestone_booked || (int) $row->has_appointment) ? 1 : 0;
            $row->sold = ((int) $row->sales_count > 0 || (int) $row->budget_accepted) ? 1 : 0;

            return $row;
        });
    }

    /**
     * Patients created in the period that did not come from a lead.
     *
     * @return Collection<int, object>
     */
    private function directPatientRows(CarbonImmutable $fromStart, CarbonImmutable $toEnd, ?int $originId): Collection
    {
        $query = Client::query()
            ->whereBetween('clients.created_at', [$fromStart, $toEnd])
            ->whereNull('clients.lead_id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('leads')
                ->whereColumn('leads.client_id', 'clients.id')
                ->whereColumn('leads.clinic_id', 'clients.clinic_id'))
            ->select('clients.id')
            ->selectSub($this->exists(Appointment::class, 'clients', fn ($q) => $q->where('appointments.status', '!=', Appointment::STATUS_CANCELLED)), 'booked')
            ->selectSub($this->exists(Appointment::class, 'clients', fn ($q) => $q->whereIn('appointments.status', [Appointment::STATUS_IN_PROGRESS, Appointment::STATUS_COMPLETED])), 'attended')
            ->selectSub($this->exists(Budget::class, 'clients'), 'budgeted')
            ->selectSub($this->exists(Budget::class, 'clients', fn ($q) => $q->where('budgets.status', Budget::STATUS_ACCEPTED)), 'budget_accepted')
            ->selectSub($this->salesAggregate('clients', 'COUNT(*)'), 'sales_count')
            ->selectSub($this->salesAggregate('clients', 'COALESCE(SUM(sales.effective_amount), 0)'), 'revenue');

        if ($originId !== null) {
            $query->where('clients.client_origin_id', $originId);
        }

        return $query->toBase()->get()->map(function ($row) {
            $row->is_lead = 0;
            $row->approached = 0;
            $row->replied = 0;
            $row->sold = ((int) $row->sales_count > 0 || (int) $row->budget_accepted) ? 1 : 0;

            return $row;
        });
    }

    /**
     * EXISTS-style 0/1 subquery on a patient-owned table, from the lead's (or patient's) creation on.
     *
     * @param  class-string  $model
     */
    private function exists(string $model, string $owner, ?callable $where = null): QueryBuilder
    {
        $table = (new $model)->getTable();
        $q = $model::query()->withoutGlobalScopes()->toBase()
            ->selectRaw('CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END')
            ->from($table);
        $this->ownedBy($q, $table, $owner);
        if ($where !== null) {
            $where($q);
        }

        return $q;
    }

    private function salesAggregate(string $owner, string $expression): QueryBuilder
    {
        $q = Sale::query()->withoutGlobalScopes()->toBase()
            ->selectRaw($expression)
            ->from('sales')
            ->where('sales.status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sales.sold_at');
        $this->ownedBy($q, 'sales', $owner, 'sold_at');

        return $q;
    }

    private function ownedBy(QueryBuilder $q, string $table, string $owner, string $dateColumn = 'created_at'): void
    {
        if ($owner === 'leads') {
            $q->whereColumn("{$table}.client_id", 'leads.client_id')
                ->whereColumn("{$table}.clinic_id", 'leads.clinic_id')
                ->whereColumn("{$table}.{$dateColumn}", '>=', 'leads.created_at');

            return;
        }
        $q->whereColumn("{$table}.client_id", 'clients.id')
            ->whereColumn("{$table}.clinic_id", 'clients.clinic_id')
            ->whereColumn("{$table}.{$dateColumn}", '>=', 'clients.created_at');
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, int|float>
     */
    private function aggregate(Collection $rows): array
    {
        return [
            'leads' => (int) $rows->sum(fn ($r) => (int) $r->is_lead),
            'patients' => $rows->count(),
            'approached' => (int) $rows->sum(fn ($r) => (int) $r->approached),
            'replied' => (int) $rows->sum(fn ($r) => (int) $r->replied),
            'booked' => (int) $rows->sum(fn ($r) => (int) $r->booked),
            'attended' => (int) $rows->sum(fn ($r) => (int) $r->attended),
            'budgeted' => (int) $rows->sum(fn ($r) => (int) $r->budgeted),
            'sold' => (int) $rows->sum(fn ($r) => (int) $r->sold),
            'sales_count' => (int) $rows->sum(fn ($r) => (int) $r->sales_count),
            'revenue' => (float) $rows->sum(fn ($r) => (float) $r->revenue),
        ];
    }

    /**
     * @param  array<string, int|float>  $a
     * @param  array<string, int|float>  $b
     * @return array<string, int|float>
     */
    private function sum(array $a, array $b): array
    {
        $out = [];
        foreach ($a as $key => $value) {
            $out[$key] = $value + ($b[$key] ?? 0);
        }

        return $out;
    }

    /**
     * @param  array<string, int|float>  $totals
     * @return list<array<string, mixed>>
     */
    private function stages(array $totals): array
    {
        $stages = [];
        $previous = null;
        foreach (self::STAGES as $key => $label) {
            $count = (int) $totals[$key];
            $stages[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'rate_from_previous' => $previous === null ? null : $this->rate($count, $previous),
                'rate_from_start' => $this->rate($count, (int) $totals['leads']),
            ];
            $previous = $count;
        }

        return $stages;
    }

    /**
     * @param  Collection<int, object>  $leads
     * @param  array<string, int|float>  $directTotals
     * @return list<array<string, mixed>>
     */
    private function breakdown(Collection $leads, string $idField, string $nameField, string $fallback, array $directTotals): array
    {
        $rows = $leads
            ->groupBy(fn ($r) => $r->{$idField} === null ? 'none' : (string) $r->{$idField})
            ->map(function (Collection $group, string $key) use ($idField, $nameField, $fallback) {
                $first = $group->first();
                $id = $first->{$idField} !== null ? (int) $first->{$idField} : null;

                return $this->row($id !== null ? (string) $id : 'unattributed', $id, $first->{$nameField} ?? $fallback, $this->aggregate($group), true);
            })
            ->sortBy([
                fn ($a, $b) => (float) $b['revenue'] <=> (float) $a['revenue'],
                fn ($a, $b) => $b['leads'] <=> $a['leads'],
                fn ($a, $b) => strcmp($a['label'], $b['label']),
            ])
            ->values();

        if ($directTotals['patients'] > 0) {
            $rows->push($this->row(self::NO_LEAD_KEY, null, 'Sem lead', $directTotals, false));
        }

        return $rows->all();
    }

    /**
     * @param  array<string, int|float>  $t
     * @return array<string, mixed>
     */
    private function row(string $key, ?int $id, string $label, array $t, bool $fromLeads): array
    {
        $base = $fromLeads ? (int) $t['leads'] : (int) $t['patients'];

        return [
            'id' => $id,
            'key' => $key,
            'label' => $label,
            'leads' => (int) $t['leads'],
            'patients' => (int) $t['patients'],
            'approached' => (int) $t['approached'],
            'replied' => (int) $t['replied'],
            'booked' => (int) $t['booked'],
            'attended' => (int) $t['attended'],
            'budgeted' => (int) $t['budgeted'],
            'sold' => (int) $t['sold'],
            'sales_count' => (int) $t['sales_count'],
            'revenue' => $this->money((float) $t['revenue']),
            'ticket_avg' => $this->ticket((float) $t['revenue'], (int) $t['sales_count']),
            // Leads → venda for lead rows; patients → venda for "Sem lead" / totals.
            'conversion_rate' => $this->rate((int) $t['sold'], $base),
        ];
    }

    private function rate(int $part, int $total): ?string
    {
        return $total > 0 ? number_format(($part / $total) * 100, 2, '.', '') : null;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function ticket(float $revenue, int $count): ?string
    {
        return $count > 0 ? $this->money($revenue / $count) : null;
    }
}
