<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CommissionEntry;
use App\Models\GoalRemuneration;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\UserGoal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Remuneração por meta: gera lançamentos em commission_entries (role = goal).
 *
 * Mês = mês civil em America/Sao_Paulo (o mesmo das metas). O que conta, por colaborador:
 *  - Novo agendamento: lead com o marco "Agendou" feito pelo colaborador (booked_by_user_id)
 *    no mês (booked_at). Não conta se a avaliação marcada na conversão foi cancelada.
 *  - Lead abordado: lead com o marco "Abordado" feito pelo colaborador no mês. Conta mesmo
 *    que o lead seja cancelado depois (a abordagem foi feita).
 *  - Valor de vendas: sem valor por ocorrência (isso é regra de comissão por venda); só bônus.
 *
 * Valor por ocorrência: um lançamento por lead. Bônus: um lançamento por métrica e mês quando o
 * realizado (pelas regras acima) chega à meta do mês (meta própria ou herdada, maior que zero).
 *
 * Como as comissões de venda: lançamentos pendentes são refeitos a cada sincronização; os pagos
 * nunca mudam e não são lançados de novo.
 */
class GoalRemunerationService
{
    public const TZ = UserGoalService::TZ;

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

        DB::transaction(function () use ($clinicId, $userId, $month, $config, $start, $end, $competence): void {
            $existing = CommissionEntry::query()->withoutGlobalScope('clinic')
                ->where('clinic_id', $clinicId)
                ->where('user_id', $userId)
                ->where('role', CommissionEntry::ROLE_GOAL)
                ->whereDate('competence_month', $competence);

            (clone $existing)->where('status', '!=', CommissionEntry::STATUS_PAID)->delete();
            $paid = (clone $existing)->where('status', CommissionEntry::STATUS_PAID)->get();

            $goal = $this->effectiveGoal($clinicId, $userId, $month);
            $base = ['clinic_id' => $clinicId, 'user_id' => $userId, 'role' => CommissionEntry::ROLE_GOAL, 'competence_month' => $competence];

            foreach (GoalRemuneration::METRICS as $metric) {
                $rule = $config[$metric];
                $per = (float) $rule->per_occurrence_amount;
                $bonus = (float) $rule->target_bonus_amount;
                if ($per <= 0 && $bonus <= 0) {
                    continue;
                }

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
            }
        });
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

        return $ids->count();
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

    /** Avaliação cancelada: o agendamento de quem converteu o lead deixa de contar. */
    public function onAppointmentStatusChanged(Appointment $appointment): void
    {
        Lead::query()->withoutGlobalScope('clinic')
            ->where('booked_appointment_id', $appointment->id)
            ->whereNotNull('booked_by_user_id')
            ->get()
            ->each(fn (Lead $lead) => $this->syncUserMonth((int) $lead->clinic_id, (int) $lead->booked_by_user_id, self::monthOf($lead->booked_at)));
    }

    /** Venda confirmada/cancelada: pode mudar o bônus da meta de vendas. */
    public function onSaleChanged(Sale $sale): void
    {
        if ($sale->sold_by_user_id === null || $sale->sold_at === null) {
            return;
        }
        $this->syncUserMonth((int) $sale->clinic_id, (int) $sale->sold_by_user_id, self::monthOf($sale->sold_at));
    }

    /**
     * @return list<int>
     */
    private function occurrenceLeadIds(int $clinicId, int $userId, string $metric, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $step = $metric === GoalRemuneration::METRIC_APPOINTMENTS ? 'booked' : 'approached';

        $query = Lead::query()->withoutGlobalScope('clinic')
            ->where('leads.clinic_id', $clinicId)
            ->where("leads.{$step}_by_user_id", $userId)
            ->whereBetween("leads.{$step}_at", [$start, $end]);

        if ($step === 'booked') {
            $query->where(fn ($q) => $q->whereNull('leads.booked_appointment_id')
                ->orWhereNotExists(fn ($sub) => $sub->selectRaw('1')->from('appointments')
                    ->whereColumn('appointments.id', 'leads.booked_appointment_id')
                    ->where('appointments.status', Appointment::STATUS_CANCELLED)));
        }

        return $query->orderBy('leads.id')->pluck('leads.id')->map(fn ($id) => (int) $id)->all();
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
