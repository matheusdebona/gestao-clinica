<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Lead;
use App\Models\LeadFollowUpAlert;
use App\Models\LeadFollowUpTier;
use App\Models\User;
use App\Notifications\StaleLeadsDigestNotification;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Lead "parado": open lead (not converted, not cancelled) with no activity for at least the
 * smallest follow-up tier of the clinic (Configurações › Alertas de acompanhamento, default 3/7/30/60 days).
 * A lead belongs to the highest tier it reached.
 */
class LeadStaleService
{
    public const SUMMARY_LIMIT = 5;

    public static function cutoff(int $days, ?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->subDays($days);
    }

    /**
     * @return Collection<int, LeadFollowUpTier>
     */
    public function tiers(?int $clinicId): Collection
    {
        if ($clinicId === null) {
            return collect();
        }

        return LeadFollowUpTier::query()->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->orderBy('days')
            ->get();
    }

    public function minDays(?int $clinicId): ?int
    {
        $min = $this->tiers($clinicId)->min('days');

        return $min !== null ? (int) $min : null;
    }

    public function count(?int $clinicId, ?CarbonImmutable $now = null): int
    {
        $min = $this->minDays($clinicId);

        return $min === null ? 0 : Lead::query()->stale(self::cutoff($min, $now))->count();
    }

    /**
     * Cumulative counts per tier ("7+" includes 30+ and 60+), same semantics as the list filter.
     *
     * @return list<array{days: int, label: string, color: string, count: int}>
     */
    public function tierCounts(?int $clinicId, ?CarbonImmutable $now = null): array
    {
        return $this->tiers($clinicId)
            ->map(fn (LeadFollowUpTier $tier): array => [
                'days' => $tier->days,
                'label' => $tier->displayLabel(),
                'color' => $tier->color,
                'count' => Lead::query()->stale(self::cutoff($tier->days, $now))->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{days: int|null, count: int, tiers: list<array<string, mixed>>, leads: list<array<string, mixed>>}
     */
    public function summary(?int $clinicId, int $limit = self::SUMMARY_LIMIT): array
    {
        $min = $this->minDays($clinicId);

        if ($min === null) {
            return ['days' => null, 'count' => 0, 'tiers' => [], 'leads' => []];
        }

        $tiers = $this->tierCounts($clinicId);
        $leads = Lead::query()->stale(self::cutoff($min))
            ->withLastActivity()
            ->with('status')
            ->orderBy('activities_max_created_at')
            ->orderBy('leads.id')
            ->limit($limit)
            ->get()
            ->map(fn (Lead $lead): array => [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'status' => $lead->status === null ? null : [
                    'id' => $lead->status->id,
                    'name' => $lead->status->name,
                    'color' => $lead->status->color,
                ],
                'last_activity_at' => $lead->lastActivityAt()?->toISOString(),
            ])
            ->values()
            ->all();

        return ['days' => $min, 'count' => $tiers[0]['count'] ?? 0, 'tiers' => $tiers, 'leads' => $leads];
    }

    /**
     * Open leads that reached a tier they were not alerted for since their last activity.
     *
     * @return list<array{lead_id: int, days: int}>
     */
    public function newCrossings(Clinic $clinic, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $tiers = $this->tiers($clinic->id)->sortByDesc('days')->values();

        if ($tiers->isEmpty()) {
            return [];
        }

        $leads = Lead::query()->where('leads.clinic_id', $clinic->id)
            ->stale(self::cutoff((int) $tiers->last()->days, $now))
            ->withLastActivity()
            ->get();

        if ($leads->isEmpty()) {
            return [];
        }

        $alerts = LeadFollowUpAlert::query()->withoutGlobalScopes()
            ->whereIn('lead_id', $leads->pluck('id'))
            ->get()
            ->keyBy(fn (LeadFollowUpAlert $alert): string => $alert->lead_id.':'.$alert->days);

        $crossings = [];
        foreach ($leads as $lead) {
            $last = $lead->lastActivityAt() ?? $now;
            $reached = $tiers->first(fn (LeadFollowUpTier $tier): bool => $last->lte(self::cutoff($tier->days, $now)));
            if ($reached === null) {
                continue;
            }
            $alert = $alerts->get($lead->id.':'.$reached->days);
            // Already alerted for this tier since the last activity (new activity re-arms it).
            if ($alert !== null && $alert->alerted_at->gte($last)) {
                continue;
            }
            $crossings[] = ['lead_id' => $lead->id, 'days' => (int) $reached->days];
        }

        return $crossings;
    }

    /** Daily digest: one in-app notification per user with leads.manage, only for new crossings. */
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
        $previousClinicId = CurrentClinic::id();
        CurrentClinic::setId($clinic->id);

        try {
            $crossings = $this->newCrossings($clinic, $now);

            if ($crossings === []) {
                return 0;
            }

            // Record first: each lead/tier pair is alerted only once (until new activity).
            DB::transaction(function () use ($crossings, $clinic, $now): void {
                foreach ($crossings as $crossing) {
                    LeadFollowUpAlert::query()->withoutGlobalScopes()->updateOrCreate(
                        ['lead_id' => $crossing['lead_id'], 'days' => $crossing['days']],
                        ['clinic_id' => $clinic->id, 'alerted_at' => $now],
                    );
                }
            });

            $labels = $this->tiers($clinic->id)->keyBy('days');
            $byTier = collect($crossings)->countBy('days')->sortKeys();
            $groups = $byTier->map(fn (int $count, int $days): array => [
                'days' => $days,
                'label' => $labels->get($days)?->displayLabel() ?? "Parados há {$days}+ dias",
                'count' => $count,
            ])->values()->all();

            $recipients = $this->recipientsForClinic($clinic->id)
                ->reject(fn (User $user): bool => $this->alreadyNotifiedToday($user, $now))
                ->values();

            if ($recipients->isEmpty()) {
                return 0;
            }

            Notification::send($recipients, new StaleLeadsDigestNotification(count($crossings), $groups));

            return $recipients->count();
        } finally {
            CurrentClinic::setId($previousClinicId);
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
            ->filter(fn (User $user) => $user->can('leads.manage'))
            ->values();
    }

    private function alreadyNotifiedToday(User $user, CarbonImmutable $now): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereDate('created_at', $now->toDateString())
            ->where('data->type', StaleLeadsDigestNotification::TYPE)
            ->exists();
    }
}
