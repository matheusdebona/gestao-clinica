<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Clinic;
use App\Models\User;
use App\Notifications\ForgottenBudgetsDigestNotification;
use App\Support\ClinicSettings;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Orçamentos esquecidos: enviados (status sent) e sem resposta — nem aceitos, nem recusados,
 * nem expirados (status ou validade vencida) — há pelo menos o menor prazo da clínica
 * (clinics.settings.budget_follow_up_days, padrão 2/5/10 dias).
 *
 * Os dias contam em dias de calendário (America/Sao_Paulo) desde o envio. Cada prazo é uma
 * "faixa": um orçamento precisa de cobrança quando entrou numa faixa e ninguém registrou um
 * WhatsApp de cobrança depois disso. Cobrar tira o orçamento da fila até a próxima faixa.
 */
class BudgetFollowUpService
{
    public const TZ = 'America/Sao_Paulo';

    /** Safety cap when evaluating tiers in PHP. */
    private const CANDIDATE_LIMIT = 500;

    /** @var array<int, list<int>> */
    private array $tiersCache = [];

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ)->startOfDay();
    }

    /**
     * @return list<int>
     */
    public function tiers(?int $clinicId = null): array
    {
        $clinicId ??= CurrentClinic::id();
        if ($clinicId === null) {
            return [];
        }

        return $this->tiersCache[$clinicId] ??= ($clinic = Clinic::query()->find($clinicId))
            ? ClinicSettings::budgetFollowUpDays($clinic)
            : [];
    }

    /**
     * Every forgotten budget of the current clinic (followed up or not), oldest first.
     *
     * @param  list<int>  $userIds  optional: only budgets created by these users
     */
    public function forgottenQuery(array $userIds = [], ?CarbonImmutable $today = null): Builder
    {
        $today ??= self::today();
        $tiers = $this->tiers();
        $query = Budget::query()->where('budgets.status', Budget::STATUS_SENT);

        if ($tiers === []) {
            return $query->whereRaw('1 = 0');
        }

        // Sent on or before (today - min tier) in the clinic's day.
        $cutoff = $today->subDays($tiers[0])->endOfDay()->utc();

        return $query
            ->whereNotNull('budgets.sent_at')
            ->where('budgets.sent_at', '<=', $cutoff)
            ->where(fn (Builder $q) => $q
                ->whereNull('budgets.valid_until')
                ->orWhere('budgets.valid_until', '>=', $today->utc()))
            ->when($userIds !== [], fn (Builder $q) => $q->whereIn('budgets.created_by_user_id', $userIds))
            ->orderBy('budgets.sent_at')
            ->orderBy('budgets.id');
    }

    /**
     * @return array{days: int, tier: int, tier_index: int, tiers: int, reached_on: string, needs_follow_up: bool}|null
     */
    public function info(Budget $budget, ?CarbonImmutable $today = null): ?array
    {
        if ($budget->status !== Budget::STATUS_SENT || $budget->sent_at === null) {
            return null;
        }
        $today ??= self::today();
        if ($budget->valid_until !== null && CarbonImmutable::parse($budget->valid_until)->lt($today->utc())) {
            return null;
        }
        $tiers = $this->tiers($budget->clinic_id);
        $sentOn = CarbonImmutable::parse($budget->sent_at)->setTimezone(self::TZ)->startOfDay();
        $days = (int) $sentOn->diffInDays($today, false);

        $index = null;
        foreach ($tiers as $i => $tier) {
            if ($days >= $tier) {
                $index = $i;
            }
        }
        if ($index === null) {
            return null;
        }

        $reachedOn = $sentOn->addDays($tiers[$index]);
        $followedUp = $budget->follow_up_sent_at !== null
            && CarbonImmutable::parse($budget->follow_up_sent_at)->setTimezone(self::TZ)->startOfDay()->gte($reachedOn);

        return [
            'days' => $days,
            'tier' => $tiers[$index],
            'tier_index' => $index,
            'tiers' => count($tiers),
            'reached_on' => $reachedOn->toDateString(),
            'needs_follow_up' => ! $followedUp,
        ];
    }

    /**
     * Budgets that reached a tier and were not followed up since (for queues and digests).
     *
     * @param  list<int>  $userIds
     * @return Collection<int, Budget>
     */
    public function needingFollowUp(array $userIds = [], ?CarbonImmutable $today = null): Collection
    {
        $today ??= self::today();

        return $this->forgottenQuery($userIds, $today)
            ->with('client')
            ->limit(self::CANDIDATE_LIMIT)
            ->get()
            ->filter(fn (Budget $budget): bool => (bool) ($this->info($budget, $today)['needs_follow_up'] ?? false))
            ->values();
    }

    /**
     * @param  list<int>  $userIds
     * @return array{count: int, total: int, items: list<array<string, mixed>>}
     */
    public function queueSummary(int $limit, array $userIds = []): array
    {
        $today = self::today();
        $pending = $this->needingFollowUp($userIds, $today);
        // Most forgotten first.
        $sorted = $pending->sortBy(fn (Budget $b) => [-($this->info($b, $today)['days'] ?? 0), $b->id])->values();

        return [
            'count' => $pending->count(),
            'total' => (clone $this->forgottenQuery($userIds, $today))->count(),
            'items' => $sorted->take($limit)->map(fn (Budget $b) => $this->present($b, $today))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Budget $budget, ?CarbonImmutable $today = null): array
    {
        return [
            'id' => $budget->id,
            'sale_id' => $budget->sale_id,
            'client_id' => $budget->client_id,
            'client' => $budget->relationLoaded('client') && $budget->client ? [
                'id' => $budget->client->id,
                'name' => $budget->client->name,
                'whatsapp' => $budget->client->whatsapp,
            ] : null,
            'version' => $budget->version,
            'effective_amount' => $budget->effective_amount,
            'sent_at' => $budget->sent_at,
            'follow_up_sent_at' => $budget->follow_up_sent_at,
            'follow_up_count' => (int) $budget->follow_up_count,
            'forgotten' => $this->info($budget, $today),
        ];
    }

    public function logWhatsApp(Budget $budget, User $user): Budget
    {
        $budget->forceFill([
            'follow_up_sent_at' => now(),
            'follow_up_sent_by_user_id' => $user->id,
            'follow_up_count' => (int) $budget->follow_up_count + 1,
        ])->save();

        return $budget->fresh(['client']);
    }

    /** 08:00 (Brasília) digest for each clinic. */
    public function runDailyDigest(?CarbonImmutable $now = null): int
    {
        $sent = 0;
        Clinic::query()->orderBy('id')->each(function (Clinic $clinic) use ($now, &$sent): void {
            $sent += $this->digestClinic($clinic, $now);
        });

        return $sent;
    }

    public function digestClinic(Clinic $clinic, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $previous = CurrentClinic::id();
        CurrentClinic::setId($clinic->id);

        try {
            $today = $now->setTimezone(self::TZ)->startOfDay();
            $pending = $this->needingFollowUp([], $today);
            if ($pending->isEmpty()) {
                return 0;
            }
            $oldest = (int) $pending->map(fn (Budget $b) => $this->info($b, $today)['days'] ?? 0)->max();
            $amount = (float) $pending->sum(fn (Budget $b) => (float) $b->effective_amount);

            $recipients = $this->recipientsForClinic($clinic->id)
                ->reject(fn (User $user): bool => $this->alreadyNotifiedToday($user, $today))
                ->values();
            if ($recipients->isEmpty()) {
                return 0;
            }

            Notification::send($recipients, new ForgottenBudgetsDigestNotification($pending->count(), $oldest, $amount));

            return $recipients->count();
        } finally {
            CurrentClinic::setId($previous);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function recipientsForClinic(int $clinicId): Collection
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->can('budgets.update'))
            ->values();
    }

    private function alreadyNotifiedToday(User $user, CarbonImmutable $today): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('created_at', '>=', $today->utc())
            ->where('data->type', ForgottenBudgetsDigestNotification::TYPE)
            ->exists();
    }
}
