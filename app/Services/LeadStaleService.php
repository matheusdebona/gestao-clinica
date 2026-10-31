<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\StaleLeadsDigestNotification;
use App\Support\ClinicSettings;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Lead "parado": open lead (not converted, not cancelled) without any activity for N days
 * (N = per-clinic setting, default 3).
 */
class LeadStaleService
{
    public const SUMMARY_LIMIT = 5;

    public static function cutoff(int $days, ?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->subDays($days);
    }

    public function count(?int $clinicId, ?CarbonImmutable $now = null): int
    {
        $days = ClinicSettings::leadStaleDaysForId($clinicId);

        return Lead::query()->stale(self::cutoff($days, $now))->count();
    }

    /**
     * @return array{days: int, count: int, leads: list<array<string, mixed>>}
     */
    public function summary(?int $clinicId, int $limit = self::SUMMARY_LIMIT): array
    {
        $days = ClinicSettings::leadStaleDaysForId($clinicId);
        $query = Lead::query()->stale(self::cutoff($days));
        $count = (clone $query)->count();

        $leads = $query->withLastActivity()
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

        return ['days' => $days, 'count' => $count, 'leads' => $leads];
    }

    /** Daily digest: one in-app notification per user with leads.manage, at most once a day. */
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
        $previousClinicId = CurrentClinic::id();
        CurrentClinic::setId($clinic->id);

        try {
            $days = ClinicSettings::leadStaleDays($clinic);
            $count = Lead::query()->where('leads.clinic_id', $clinic->id)->stale(self::cutoff($days, $now))->count();

            if ($count === 0) {
                return 0;
            }

            $recipients = $this->recipientsForClinic($clinic->id)
                ->reject(fn (User $user): bool => $this->alreadyNotifiedToday($user, $now))
                ->values();

            if ($recipients->isEmpty()) {
                return 0;
            }

            Notification::send($recipients, new StaleLeadsDigestNotification($count, $days));

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

    private function alreadyNotifiedToday(User $user, ?CarbonImmutable $now): bool
    {
        $day = ($now ?? CarbonImmutable::now())->toDateString();

        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereDate('created_at', $day)
            ->where('data->type', StaleLeadsDigestNotification::TYPE)
            ->exists();
    }
}
