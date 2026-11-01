<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\User;
use App\Notifications\AppointmentConfirmationDigestNotification;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * "Confirmar amanhã": the day before each session the team confirms it by WhatsApp (wa.me link
 * with the clinic template, no automatic sending) and records the answer.
 * Days are America/Sao_Paulo calendar days; timestamps are stored in UTC.
 */
class AppointmentConfirmationService
{
    public const TZ = 'America/Sao_Paulo';

    /** Scheduled (not started, not cancelled) sessions of a clinic day, by time. */
    public function forDay(CarbonImmutable $day): EloquentCollection
    {
        [$start, $end] = $this->dayBounds($day);

        return Appointment::query()
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->whereBetween('scheduled_at', [$start, $end])
            ->with(['client', 'professionalUser', 'confirmationSentBy', 'confirmationStatusBy'])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();
    }

    public function tomorrow(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->setTimezone(self::TZ)->addDay()->startOfDay();
    }

    /**
     * @return array<string, int>
     */
    public function counts(EloquentCollection $appointments): array
    {
        $counts = array_fill_keys(Appointment::CONFIRMATION_STATUSES, 0);
        foreach ($appointments as $appointment) {
            $counts[$appointment->confirmation_status ?? Appointment::CONFIRMATION_PENDING]++;
        }

        return ['total' => $appointments->count()] + $counts;
    }

    public function setStatus(Appointment $appointment, string $status, User $by): Appointment
    {
        $appointment->forceFill([
            'confirmation_status' => $status,
            'confirmation_status_at' => now(),
            'confirmation_status_by_user_id' => $by->id,
        ])->save();

        return $appointment;
    }

    /**
     * Scheduler (08:00 Brasília): internal digest of tomorrow's sessions still to confirm.
     */
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
            $day = $this->tomorrow($now);
            $counts = $this->counts($this->forDay($day));
            $toConfirm = $counts[Appointment::CONFIRMATION_PENDING] + $counts[Appointment::CONFIRMATION_NOT_CONFIRMED];
            if ($toConfirm === 0) {
                return 0;
            }

            $recipients = $this->recipientsForClinic($clinic->id)
                ->reject(fn (User $user): bool => $this->alreadyNotifiedToday($user, $now))
                ->values();
            if ($recipients->isEmpty()) {
                return 0;
            }

            Notification::send($recipients, new AppointmentConfirmationDigestNotification(
                $day->toDateString(),
                $counts['total'],
                $toConfirm,
            ));

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
            ->filter(fn (User $user) => $user->can('appointments.manage'))
            ->values();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} UTC bounds of the clinic day
     */
    public function dayBounds(CarbonImmutable $day): array
    {
        $local = CarbonImmutable::parse($day->format('Y-m-d'), self::TZ);

        return [$local->startOfDay()->utc(), $local->endOfDay()->utc()];
    }

    private function alreadyNotifiedToday(User $user, CarbonImmutable $now): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereDate('created_at', $now->toDateString())
            ->where('data->type', AppointmentConfirmationDigestNotification::TYPE)
            ->exists();
    }
}
