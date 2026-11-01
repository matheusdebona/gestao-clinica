<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CommissionEntry;
use App\Models\GoalRemuneration;
use App\Models\Lead;
use App\Models\RemunerationTier;
use App\Models\Sale;
use App\Models\User;
use App\Models\UserGoal;
use App\Services\Finance\SaleEconomics;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Remuneração por meta: gera lançamentos em commission_entries (role = goal).
 *
 * Mês = mês civil em America/Sao_Paulo (o mesmo das metas). O que conta, por colaborador:
 *  - Novo agendamento: lead com o marco "Agendou" feito pelo colaborador (booked_by_user_id)
 *    no mês (booked_at). Não conta se a avaliação marcada na conversão foi cancelada.
 *  - Lead abordado: lead com o marco "Abordado" feito pelo colaborador no mês. Não conta se o
 *    lead for cancelado depois, nem quando o "Abordado" veio junto com a conversão ("Agendou"
 *    sem abordagem anterior): converter paga só o agendamento.
 *  A contagem é a mesma da tela de Metas (UserGoalService::milestoneQuery).
 *  - Valor de vendas: sem valor por ocorrência (isso é regra de comissão por venda); só bônus.
 *
 * Valor por ocorrência: um lançamento por lead. Bônus: um lançamento por métrica e mês quando o
 * realizado (pelas regras acima) chega à meta do mês (meta própria ou herdada, maior que zero).
 *
 * Faixas de agendamentos (RemunerationTier kind = appointments, da clínica): pela quantidade de
 * novos agendamentos que contam no mês, vale só a maior faixa atingida: valor fixo + % sobre as
 * vendas confirmadas no mês dos pacientes desses agendamentos. Soma-se ao valor por ocorrência.
 *
 * Faturamento (RemunerationTier kind = revenue, por colaborador; role = revenue): pelo faturamento
 * total da clínica no mês (mesma receita da DRE: valor efetivo das vendas confirmadas + extras
 * cobrados nas sessões delas), vale só a maior faixa: valor fixo + % do faturamento total.
 *
 * Como as comissões de venda: lançamentos pendentes são refeitos a cada sincronização; os pagos
 * nunca mudam e não são lançados de novo (faixa já paga no mês não gera diferença, mesmo que o
 * realizado passe depois para uma faixa maior).
 *
 * Colaborador desativado não ganha (metas, faixas e faturamento): na sincronização os pendentes
 * dele saem e nada novo é lançado; os pagos ficam.
 *
 * Cada evento conta no mês civil de São Paulo em que aconteceu: o agendamento no mês do "Agendou"
 * e a venda no mês em que foi vendida (sold_at). A base do % da faixa de agendamentos de um mês só
 * inclui vendas daquele mês; venda de outro mês de um paciente agendado antes não entra nela.
 */
class GoalRemunerationService
{
    public const TZ = UserGoalService::TZ;

    /** Durante "Recalcular mês" o faturamento e as faixas são refeitos uma vez, no fim. */
    private static bool $deferSaleFanout = false;

    public function __construct(private readonly SaleEconomics $economics) {}

    /**
     * Executa $fn sem refazer faturamento/faixas a cada venda (o chamador refaz o mês no fim).
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public static function deferringSaleFanout(callable $fn): mixed
    {
        $previous = self::$deferSaleFanout;
        self::$deferSaleFanout = true;
        try {
            return $fn();
        } finally {
            self::$deferSaleFanout = $previous;
        }
    }

    /**
     * Troca as faixas de um uso (agendamentos da clínica ou faturamento de um colaborador).
     *
     * @param  list<array{threshold: float, fixed_amount: float, percent: float}>  $tiers
     */
    public function replaceTiers(int $clinicId, string $kind, ?int $userId, array $tiers, ?int $editorId): void
    {
        DB::transaction(function () use ($clinicId, $kind, $userId, $tiers, $editorId): void {
            RemunerationTier::query()->withoutGlobalScope('clinic')
                ->where('clinic_id', $clinicId)
                ->where('kind', $kind)
                ->when($userId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $userId))
                ->delete();
            foreach ($tiers as $tier) {
                RemunerationTier::query()->create([
                    'clinic_id' => $clinicId,
                    'kind' => $kind,
                    'user_id' => $userId,
                    'threshold' => number_format($tier['threshold'], 2, '.', ''),
                    'fixed_amount' => number_format($tier['fixed_amount'], 2, '.', ''),
                    'percent' => number_format($tier['percent'], 4, '.', ''),
                    'updated_by_user_id' => $editorId,
                ]);
            }
        });
    }

    /**
     * @return Collection<int, RemunerationTier>
     */
    public function tiers(int $clinicId, string $kind, ?int $userId = null): Collection
    {
        return RemunerationTier::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('kind', $kind)
            ->when($userId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $userId))
            ->orderBy('threshold')
            ->get();
    }

    /**
     * @return Collection<string, GoalRemuneration> keyed by metric, all metrics present
     */
    public function config(int $clinicId): Collection
    {
        $rows = GoalRemuneration::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->get()
            ->keyBy('metric');

        return collect(GoalRemuneration::METRICS)->mapWithKeys(fn (string $metric) => [
            $metric => $rows->get($metric) ?? new GoalRemuneration(['clinic_id' => $clinicId, 'metric' => $metric]),
        ]);
    }

    public static function monthOf(CarbonInterface|string|null $at): string
    {
        return CarbonImmutable::parse($at ?? now())->setTimezone(self::TZ)->format('Y-m');
    }

    /**
     * Refaz os lançamentos pendentes de metas de um colaborador no mês.
     */
    public function syncUserMonth(int $clinicId, int $userId, string $month): void
    {
        $config = $this->config($clinicId);
        [$start, $end] = UserGoalService::monthBounds($month);
        $competence = $month.'-01';

        $active = $this->isActive($userId);

        DB::transaction(function () use ($clinicId, $userId, $month, $config, $start, $end, $competence, $active): void {
            $existing = CommissionEntry::query()->withoutGlobalScope('clinic')
                ->where('clinic_id', $clinicId)
                ->where('user_id', $userId)
                ->where('role', CommissionEntry::ROLE_GOAL)
                ->whereDate('competence_month', $competence);

            (clone $existing)->where('status', '!=', CommissionEntry::STATUS_PAID)->delete();
            if (! $active) {
                return; // Desativado não ganha; pagos ficam.
            }
            $paid = (clone $existing)->where('status', CommissionEntry::STATUS_PAID)->get();

            $goal = $this->effectiveGoal($clinicId, $userId, $month);
            $base = ['clinic_id' => $clinicId, 'user_id' => $userId, 'role' => CommissionEntry::ROLE_GOAL, 'competence_month' => $competence];
            $appointmentTiers = $this->tiers($clinicId, RemunerationTier::KIND_APPOINTMENTS);

            foreach (GoalRemuneration::METRICS as $metric) {
                $rule = $config[$metric];
                $per = (float) $rule->per_occurrence_amount;
                $bonus = (float) $rule->target_bonus_amount;
                $tiers = $metric === GoalRemuneration::METRIC_APPOINTMENTS ? $appointmentTiers : collect();
                if ($per <= 0 && $bonus <= 0 && $tiers->isEmpty()) {
                    continue;
                }
                $leadIds = [];

                $actual = 0.0;
                if ($metric === GoalRemuneration::METRIC_SALES) {
                    $actual = $this->salesTotal($clinicId, $userId, $start, $end);
                } else {
                    $leadIds = $this->occurrenceLeadIds($clinicId, $userId, $metric, $start, $end);
                    $actual = (float) count($leadIds);
                    if ($per > 0 && in_array($metric, GoalRemuneration::PER_OCCURRENCE_METRICS, true)) {
                        $paidLeads = $paid->where('goal_metric', $metric)
                            ->where('goal_kind', CommissionEntry::GOAL_KIND_OCCURRENCE)
                            ->pluck('lead_id')->map(fn ($id) => (int) $id)->all();
                        foreach (array_diff($leadIds, $paidLeads) as $leadId) {
                            CommissionEntry::query()->create($base + [
                                'lead_id' => $leadId,
                                'goal_metric' => $metric,
                                'goal_kind' => CommissionEntry::GOAL_KIND_OCCURRENCE,
                                'base_amount' => number_format($per, 2, '.', ''),
                                'amount' => number_format($per, 2, '.', ''),
                                'status' => CommissionEntry::STATUS_PENDING,
                            ]);
                        }
                    }
                }

                $target = $this->target($goal, $metric);
                $bonusPaid = $paid->where('goal_metric', $metric)->where('goal_kind', CommissionEntry::GOAL_KIND_BONUS)->isNotEmpty();
                if ($bonus > 0 && $target !== null && $target > 0 && round($actual, 2) >= round($target, 2) && ! $bonusPaid) {
                    CommissionEntry::query()->create($base + [
                        'goal_metric' => $metric,
                        'goal_kind' => CommissionEntry::GOAL_KIND_BONUS,
                        'base_amount' => number_format($target, 2, '.', ''),
                        'amount' => number_format($bonus, 2, '.', ''),
                        'status' => CommissionEntry::STATUS_PENDING,
                    ]);
                }

                // Faixas (só agendamentos): maior faixa atingida, fixo + % das vendas dos pacientes.
                $tier = RemunerationTier::reached($tiers, $actual);
                $tierPaid = $paid->where('goal_metric', $metric)->where('goal_kind', CommissionEntry::GOAL_KIND_TIER)->isNotEmpty();
                if ($tier !== null && ! $tierPaid) {
                    $tierBase = (float) $tier->percent > 0 ? $this->patientsSalesRevenue($clinicId, $leadIds, $start, $end) : 0.0;
                    $amount = $tier->amountFor($tierBase);
                    if ($amount > 0) {
                        CommissionEntry::query()->create($base + [
                            'goal_metric' => $metric,
                            'goal_kind' => CommissionEntry::GOAL_KIND_TIER,
                            'tier_threshold' => $tier->threshold,
                            'base_amount' => number_format($tierBase, 2, '.', ''),
                            'rate_percent' => (float) $tier->percent > 0 ? $tier->percent : null,
                            'amount' => number_format($amount, 2, '.', ''),
                            'status' => CommissionEntry::STATUS_PENDING,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Refaz os lançamentos pendentes de faturamento (role = revenue) do mês para quem tem faixas
     * (ou já tinha lançamento no mês). Retorna quantos colaboradores foram processados.
     */
    public function syncRevenueMonth(int $clinicId, string $month): int
    {
        $competence = $month.'-01';
        $tiersByUser = RemunerationTier::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('kind', RemunerationTier::KIND_REVENUE)
            ->whereNotNull('user_id')
            ->get()
            ->groupBy('user_id');

        $userIds = $tiersByUser->keys()->map(fn ($id) => (int) $id)
            ->merge(CommissionEntry::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinicId)
                ->where('role', CommissionEntry::ROLE_REVENUE)->whereDate('competence_month', $competence)
                ->pluck('user_id')->map(fn ($id) => (int) $id))
            ->unique()->values();
        if ($userIds->isEmpty()) {
            return 0;
        }

        $revenue = $tiersByUser->isNotEmpty() ? $this->clinicRevenue($clinicId, $month) : 0.0;
        $activeIds = User::query()->withoutGlobalScopes()->whereIn('id', $userIds)->where('is_active', true)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($clinicId, $competence, $tiersByUser, $userIds, $revenue, $activeIds): void {
            foreach ($userIds as $userId) {
                $existing = CommissionEntry::query()->withoutGlobalScope('clinic')
                    ->where('clinic_id', $clinicId)
                    ->where('user_id', $userId)
                    ->where('role', CommissionEntry::ROLE_REVENUE)
                    ->whereDate('competence_month', $competence);
                (clone $existing)->where('status', '!=', CommissionEntry::STATUS_PAID)->delete();
                if (! in_array($userId, $activeIds, true)
                    || (clone $existing)->where('status', CommissionEntry::STATUS_PAID)->exists()) {
                    continue; // Desativado não ganha; faixa paga não gera diferença.
                }

                $tier = RemunerationTier::reached($tiersByUser->get($userId, collect()), $revenue);
                $amount = $tier?->amountFor($revenue) ?? 0.0;
                if ($tier === null || $amount <= 0) {
                    continue;
                }
                CommissionEntry::query()->create([
                    'clinic_id' => $clinicId,
                    'user_id' => $userId,
                    'role' => CommissionEntry::ROLE_REVENUE,
                    'competence_month' => $competence,
                    'goal_kind' => CommissionEntry::GOAL_KIND_TIER,
                    'tier_threshold' => $tier->threshold,
                    'base_amount' => number_format($revenue, 2, '.', ''),
                    'rate_percent' => (float) $tier->percent > 0 ? $tier->percent : null,
                    'amount' => number_format($amount, 2, '.', ''),
                    'status' => CommissionEntry::STATUS_PENDING,
                ]);
            }
        });

        return $userIds->count();
    }

    /**
     * Faturamento da clínica no mês civil de São Paulo, com a definição de receita da DRE
     * (Receita líquida): valor efetivo das vendas confirmadas + extras cobrados nas sessões delas.
     */
    public function clinicRevenue(int $clinicId, string $month): float
    {
        [$start, $end] = UserGoalService::monthBounds($month);

        return $this->salesRevenue(Sale::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereBetween('sold_at', [$start, $end]));
    }

    /**
     * Base do % da faixa de agendamentos: vendas confirmadas no mês dos pacientes dos leads contados.
     *
     * @param  list<int>  $leadIds
     */
    private function patientsSalesRevenue(int $clinicId, array $leadIds, CarbonImmutable $start, CarbonImmutable $end): float
    {
        if ($leadIds === []) {
            return 0.0;
        }
        $clientIds = Lead::query()->withoutGlobalScope('clinic')
            ->whereIn('id', $leadIds)->whereNotNull('client_id')->pluck('client_id')->unique()->values()->all();
        if ($clientIds === []) {
            return 0.0;
        }

        return $this->salesRevenue(Sale::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereIn('client_id', $clientIds)
            ->whereBetween('sold_at', [$start, $end]));
    }

    /**
     * Receita de um conjunto de vendas como na margem de contribuição/DRE.
     *
     * @param  Builder<Sale>  $sales
     */
    private function salesRevenue(Builder $sales): float
    {
        $total = 0.0;
        $sales->with(['treatment.appointments.consumptions'])
            ->chunkById(200, function ($rows) use (&$total): void {
                foreach ($rows as $sale) {
                    $total += (float) $sale->effective_amount;
                    foreach ($this->economics->completedAppointments($sale) as $appointment) {
                        foreach ($appointment->consumptions as $c) {
                            $total += (float) $c->charged_amount;
                        }
                    }
                }
            });

        return round($total, 2);
    }

    /**
     * Sincroniza todos que tiveram eventos de meta no mês ou já têm lançamentos de meta nele.
     */
    public function syncMonth(int $clinicId, string $month): int
    {
        [$start, $end] = UserGoalService::monthBounds($month);

        $ids = collect()
            ->merge(Lead::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinicId)
                ->whereBetween('booked_at', [$start, $end])->whereNotNull('booked_by_user_id')->pluck('booked_by_user_id'))
            ->merge(Lead::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinicId)
                ->whereBetween('approached_at', [$start, $end])->whereNotNull('approached_by_user_id')->pluck('approached_by_user_id'))
            ->merge(Sale::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinicId)
                ->where('status', Sale::STATUS_CONFIRMED)->whereBetween('sold_at', [$start, $end])->pluck('sold_by_user_id'))
            ->merge(CommissionEntry::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinicId)
                ->where('role', CommissionEntry::ROLE_GOAL)->whereDate('competence_month', $month.'-01')->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($ids as $userId) {
            $this->syncUserMonth($clinicId, $userId, $month);
        }

        return $ids->count() + $this->syncRevenueMonth($clinicId, $month);
    }

    /** Lead marcado (Abordado/Agendou) pelo colaborador. */
    public function onLeadMilestones(Lead $lead, array $steps): void
    {
        foreach (array_intersect($steps, ['approached', 'booked']) as $step) {
            $userId = $lead->getAttribute("{$step}_by_user_id");
            $at = $lead->getAttribute("{$step}_at");
            if ($userId !== null && $at !== null) {
                $this->syncUserMonth((int) $lead->clinic_id, (int) $userId, self::monthOf($at));
            }
        }
    }

    /**
     * Colaborador ativado/desativado: refaz o mês atual (metas e faturamento). Meses anteriores
     * só mudam com "Recalcular mês"; pagos nunca mudam.
     */
    public function onUserActiveChanged(User $user): void
    {
        if ($user->clinic_id === null) {
            return;
        }
        $month = self::monthOf(now());
        $this->syncUserMonth((int) $user->clinic_id, (int) $user->id, $month);
        $this->syncRevenueMonth((int) $user->clinic_id, $month);
    }

    private function isActive(int $userId): bool
    {
        return (bool) User::query()->withoutGlobalScopes()->whereKey($userId)->value('is_active');
    }

    /** Lead cancelado: a abordagem dele deixa de pagar (pendentes saem; pagos ficam). */
    public function onLeadCancelled(Lead $lead): void
    {
        if ($lead->approached_by_user_id !== null && $lead->approached_at !== null) {
            $this->syncUserMonth((int) $lead->clinic_id, (int) $lead->approached_by_user_id, self::monthOf($lead->approached_at));
        }
    }

    /** Avaliação cancelada: o agendamento de quem converteu o lead deixa de contar. */
    public function onAppointmentStatusChanged(Appointment $appointment): void
    {
        Lead::query()->withoutGlobalScope('clinic')
            ->where('booked_appointment_id', $appointment->id)
            ->whereNotNull('booked_by_user_id')
            ->get()
            ->each(fn (Lead $lead) => $this->syncUserMonth((int) $lead->clinic_id, (int) $lead->booked_by_user_id, self::monthOf($lead->booked_at)));
    }

    /**
     * Venda confirmada/cancelada: pode mudar o bônus da meta de vendas, a faixa de agendamentos
     * de quem converteu o paciente (base do %) e o faturamento da clínica.
     */
    public function onSaleChanged(Sale $sale): void
    {
        if ($sale->sold_at === null) {
            return;
        }
        if ($sale->sold_by_user_id !== null) {
            $this->syncUserMonth((int) $sale->clinic_id, (int) $sale->sold_by_user_id, self::monthOf($sale->sold_at));
        }
        $this->onSaleRevenueChanged($sale);
    }

    /** Receita da venda mudou (venda ou extra cobrado em sessão): faixas com % e faturamento. */
    public function onSaleRevenueChanged(Sale $sale): void
    {
        if (self::$deferSaleFanout || $sale->sold_at === null) {
            return;
        }
        $clinicId = (int) $sale->clinic_id;
        $month = self::monthOf($sale->sold_at);

        if ($sale->client_id !== null && RemunerationTier::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)->where('kind', RemunerationTier::KIND_APPOINTMENTS)
            ->where('percent', '>', 0)->exists()) {
            Lead::query()->withoutGlobalScope('clinic')
                ->where('clinic_id', $clinicId)
                ->where('client_id', $sale->client_id)
                ->whereNotNull('booked_by_user_id')
                ->pluck('booked_by_user_id')
                ->unique()
                ->each(fn ($id) => $this->syncUserMonth($clinicId, (int) $id, $month));
        }

        $this->syncRevenueMonth($clinicId, $month);
    }

    /**
     * @return list<int>
     */
    private function occurrenceLeadIds(int $clinicId, int $userId, string $metric, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $step = $metric === GoalRemuneration::METRIC_APPOINTMENTS ? 'booked' : 'approached';

        // Mesma contagem da tela de Metas.
        return UserGoalService::milestoneQuery($clinicId, $step, $userId, $start, $end)
            ->orderBy('leads.id')->pluck('leads.id')->map(fn ($id) => (int) $id)->all();
    }

    private function salesTotal(int $clinicId, int $userId, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) Sale::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('status', Sale::STATUS_CONFIRMED)
            ->where('sold_by_user_id', $userId)
            ->whereBetween('sold_at', [$start, $end])
            ->sum('effective_amount');
    }

    private function effectiveGoal(int $clinicId, int $userId, string $month): ?UserGoal
    {
        return UserGoal::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('user_id', $userId)
            ->where('month', '<=', $month)
            ->orderByDesc('month')
            ->first();
    }

    private function target(?UserGoal $goal, string $metric): ?float
    {
        $value = match ($metric) {
            GoalRemuneration::METRIC_APPOINTMENTS => $goal?->appointments_target,
            GoalRemuneration::METRIC_APPROACHED => $goal?->approached_target,
            GoalRemuneration::METRIC_SALES => $goal?->sales_target,
        };

        return $value === null ? null : (float) $value;
    }
}
