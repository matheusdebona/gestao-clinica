<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\LeadDailyCheckin;
use App\Models\User;
use App\Notifications\DailyReviewReminderNotification;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;

/** End-of-day reminder: pending follow-ups and/or missing check-in, one per user per day. */
class LeadDailyReviewReminder
{
    public function __construct(
        private readonly LeadDailyAnalysisService $analysis,
        private readonly LeadStaleService $stale,
    ) {}

    public function run(): int
    {
        $sent = 0;
        Clinic::query()->orderBy('id')->each(function (Clinic $clinic) use (&$sent): void {
            $previous = CurrentClinic::id();
            CurrentClinic::setId($clinic->id);
            try {
                foreach ($this->stale->recipientsForClinic($clinic->id) as $user) {
                    $sent += $this->remind($user, $clinic->id) ? 1 : 0;
                }
            } finally {
                CurrentClinic::setId($previous);
            }
        });

        return $sent;
    }

    private function remind(User $user, int $clinicId): bool
    {
        $today = LeadDailyAnalysisService::today();
        [$dayStart] = LeadDailyAnalysisService::bounds($today, $today);

        $already = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('created_at', '>=', $dayStart)
            ->where('data->type', DailyReviewReminderNotification::TYPE)
            ->exists();
        if ($already) {
            return false;
        }

        $pending = $this->analysis->followUpQueue(
            ['user_ids' => [$user->id], 'origin_ids' => [], 'status_ids' => [], 'stale_days' => null],
            $clinicId,
        )['count'];
        $checkinDone = LeadDailyCheckin::query()->where('user_id', $user->id)->whereDate('day', $today)->exists();

        if ($pending === 0 && $checkinDone) {
            return false;
        }

        $user->notify(new DailyReviewReminderNotification($pending, $checkinDone));

        return true;
    }
}
