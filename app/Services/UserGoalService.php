<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\User;
use App\Models\UserGoal;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Metas mensais por colaborador.
 *
 * Realizado no mês (America/Sao_Paulo), com as mesmas regras da remuneração por meta
 * (ver milestoneQuery, usada pela tela e pelo pagamento):
 *  - agendamentos: leads marcados como "Agendou" pelo usuário (leads.booked_at/booked_by_user_id),
 *    exceto quando a avaliação marcada na conversão foi cancelada;
 *  - vendas: soma do valor efetivo das vendas confirmadas do usuário (sold_by_user_id, sold_at);
 *  - leads abordados: leads marcados como "Abordado" pelo usuário (approached_at/approached_by_user_id),
 *    exceto lead cancelado e o "Abordado" implícito da conversão (marcado junto com o "Agendou").
 *
 * Um mês sem meta própria herda a meta mais recente anterior (a administração define uma vez e
 * só muda quando precisar). Quem não vê leads/vendas não recebe esses números.
 */
class UserGoalService
{
    public const TZ = 'America/Sao_Paulo';

    public const METRICS = ['appointments', 'sales', 'approached'];

    public static function currentMonth(): string
    {
        return CarbonImmutable::now(self::TZ)->format('Y-m');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} UTC bounds
     */
    public static function monthBounds(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00', self::TZ);

        return [$start->utc(), $start->endOfMonth()->utc()];
    }

    public function effectiveGoal(int $userId, string $month): ?UserGoal
    {
        return UserGoal::query()
            ->where('user_id', $userId)
            ->where('month', '<=', $month)
            ->orderByDesc('month')
            ->first();
    }

    /**
     * @param  array{appointments_target?: int|null, sales_target?: string|float|null, approached_target?: int|null}  $targets
     */
    public function save(User $user, string $month, array $targets, User $editor): UserGoal
    {
        $goal = UserGoal::query()->updateOrCreate(
            ['clinic_id' => CurrentClinic::id(), 'user_id' => $user->id, 'month' => $month],
            [
                'appointments_target' => $targets['appointments_target'] ?? null,
                'sales_target' => $targets['sales_target'] ?? null,
                'approached_target' => $targets['approached_target'] ?? null,
                'updated_by_user_id' => $editor->id,
            ],
        );

        // A meta muda o bônus "ao bater a meta": refaz o mês editado e o atual (que pode herdá-la).
        $remuneration = app(GoalRemunerationService::class);
        foreach (array_unique([$month, self::currentMonth()]) as $affected) {
            if ($affected >= $month) {
                $remuneration->syncUserMonth((int) $goal->clinic_id, $user->id, $affected);
            }
        }

        return $goal;
    }

    /**
     * @return array<string, mixed>
     */
    public function progress(User $user, string $month, User $viewer): array
    {
        $goal = $this->effectiveGoal($user->id, $month);
        [$start, $end] = self::monthBounds($month);
        $canLeads = $viewer->can('leads.view');
        $canSales = $viewer->can('sales.view');

        $metrics = [];
        if ($canLeads) {
            $metrics[] = $this->metric('appointments', 'Agendamentos', $goal?->appointments_target, $this->milestoneCount('booked', $user->id, $start, $end));
        }
        if ($canSales) {
            $metrics[] = $this->metric('sales', 'Valor de vendas', $goal?->sales_target !== null ? (float) $goal->sales_target : null, $this->salesTotal($user->id, $start, $end), true);
        }
        if ($canLeads) {
            $metrics[] = $this->metric('approached', 'Leads abordados', $goal?->approached_target, $this->milestoneCount('approached', $user->id, $start, $end));
        }

        return [
            'user' => ['id' => $user->id, 'name' => $user->name],
            'month' => $month,
            'has_goal' => $goal !== null && collect($metrics)->contains(fn ($m) => $m['target'] !== null),
            'inherited_from' => $goal !== null && $goal->month !== $month ? $goal->month : null,
            'targets' => [
                'appointments_target' => $goal?->appointments_target,
                'sales_target' => $canSales ? $goal?->sales_target : null,
                'approached_target' => $goal?->approached_target,
            ],
            'expected_percent' => $this->expectedPercent($month),
            'metrics' => $metrics,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function team(string $month, User $viewer): array
    {
        return $this->teamUsers()
            ->map(fn (User $user) => $this->progress($user, $month, $viewer))
            ->values()
            ->all();
    }

    /**
     * Active users who work leads or sales (estoque/profissional ficam de fora).
     *
     * @return Collection<int, User>
     */
    public function teamUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->can('leads.view') || $user->can('sales.view'))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function metric(string $key, string $label, int|float|null $target, int|float $actual, bool $money = false): array
    {
        $percent = $target !== null && $target > 0 ? (int) floor(($actual / $target) * 100) : null;
        $remaining = $target !== null ? max(0, $target - $actual) : null;

        return [
            'key' => $key,
            'label' => $label,
            'money' => $money,
            'target' => $target === null ? null : ($money ? number_format((float) $target, 2, '.', '') : (int) $target),
            'actual' => $money ? number_format((float) $actual, 2, '.', '') : (int) $actual,
            'remaining' => $remaining === null ? null : ($money ? number_format((float) $remaining, 2, '.', '') : (int) $remaining),
            'percent' => $percent,
        ];
    }

    private function milestoneCount(string $step, int $userId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return self::milestoneQuery((int) CurrentClinic::id(), $step, $userId, $start, $end)->count();
    }

    /**
     * Leads que contam para o colaborador no mês, no marco "booked" (Agendou) ou "approached"
     * (Abordado). Única regra de contagem: usada pela tela de Metas e pela remuneração por meta.
     *
     *  - Agendou: não conta se a avaliação marcada na conversão foi cancelada.
     *  - Abordado: não conta se o lead foi cancelado, nem quando o "Abordado" só existe porque a
     *    conversão o marcou junto (mesmo instante e mesma pessoa do "Agendou"): converter não é
     *    uma abordagem. Abordado antes (ou por outra pessoa) continua contando.
     */
    public static function milestoneQuery(int $clinicId, string $step, int $userId, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        if (! in_array($step, ['booked', 'approached'], true)) {
            throw new \InvalidArgumentException("Unknown goal milestone {$step}");
        }

        $query = Lead::query()->withoutGlobalScope('clinic')
            ->where('leads.clinic_id', $clinicId)
            ->where("leads.{$step}_by_user_id", $userId)
            ->whereBetween("leads.{$step}_at", [$start, $end]);

        if ($step === 'booked') {
            return $query->where(fn (Builder $q) => $q->whereNull('leads.booked_appointment_id')
                ->orWhereNotExists(fn ($sub) => $sub->selectRaw('1')->from('appointments')
                    ->whereColumn('appointments.id', 'leads.booked_appointment_id')
                    ->where('appointments.status', Appointment::STATUS_CANCELLED)));
        }

        return $query->whereNull('leads.cancelled_at')
            ->where(fn (Builder $q) => $q->whereNull('leads.booked_at')
                ->orWhereNull('leads.booked_by_user_id')
                ->orWhereColumn('leads.booked_at', '!=', 'leads.approached_at')
                ->orWhereColumn('leads.booked_by_user_id', '!=', 'leads.approached_by_user_id'));
    }

    private function salesTotal(int $userId, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) Sale::query()
            ->where('sales.status', Sale::STATUS_CONFIRMED)
            ->where('sales.sold_by_user_id', $userId)
            ->whereBetween('sales.sold_at', [$start, $end])
            ->sum('sales.effective_amount');
    }

    /** Share of the month already elapsed (pace reference), 100 for past months. */
    private function expectedPercent(string $month): int
    {
        $now = CarbonImmutable::now(self::TZ);
        $current = $now->format('Y-m');
        if ($month < $current) {
            return 100;
        }
        if ($month > $current) {
            return 0;
        }

        return (int) floor(($now->day / $now->daysInMonth) * 100);
    }
}
