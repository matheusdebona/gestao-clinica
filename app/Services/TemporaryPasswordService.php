<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use App\Support\TemporaryPasswordGenerator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class TemporaryPasswordService
{
    /**
     * Issue a temporary password for the e-mail, if an active account exists.
     *
     * Never reveals whether the account exists: callers always answer with the
     * same neutral message. The temporary password REPLACES the current one
     * right away (the old password stops working) and the account must set a
     * new password on the next login. It expires after the configured minutes;
     * after that only a new request lets the person in.
     */
    public function request(string $email): void
    {
        $email = Str::lower(trim($email));
        $key = 'forgot-password:'.sha1($email);
        $maxAttempts = (int) config('auth.temporary_password.per_email_attempts', 3);
        $decay = (int) config('auth.temporary_password.per_email_decay_minutes', 15) * 60;

        // Hash on every path so response time does not hint at account existence.
        $plain = TemporaryPasswordGenerator::generate();
        $hash = Hash::make($plain);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }
        RateLimiter::hit($key, $decay);

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if (! $user || ! $user->is_active) {
            return;
        }

        $minutes = (int) config('auth.temporary_password.expire', 60);

        // A new request replaces the current password and any previous temporary one.
        // The same hash is kept in temporary_password_hash to recognise it at login.
        $user->forceFill([
            'password' => $hash,
            'temporary_password_hash' => $hash,
            'temporary_password_expires_at' => now()->addMinutes($minutes),
            'must_change_password' => true,
        ])->save();

        $user->notify(new TemporaryPasswordNotification($plain, $minutes));
    }

    public function hasValidTemporaryPassword(User $user): bool
    {
        return $user->temporary_password_hash !== null
            && $user->temporary_password_expires_at !== null
            && $user->temporary_password_expires_at->isFuture();
    }

    /** True while the account password is still the issued temporary password. */
    public function passwordIsTemporary(User $user): bool
    {
        return $user->temporary_password_hash !== null
            && is_string($user->password)
            && hash_equals($user->temporary_password_hash, $user->password);
    }

    public function matches(User $user, string $plain): bool
    {
        return $this->hasValidTemporaryPassword($user)
            && Hash::check($plain, $user->temporary_password_hash);
    }

    /** Drop an expired temporary password (lazy cleanup). */
    public function clearIfExpired(User $user): void
    {
        if ($user->temporary_password_hash !== null
            && ! $this->passwordIsTemporary($user)
            && ! $this->hasValidTemporaryPassword($user)) {
            $this->clear($user);
        }
    }

    public function clear(User $user, bool $alsoFlag = false): void
    {
        $attributes = [
            'temporary_password_hash' => null,
            'temporary_password_expires_at' => null,
        ];
        if ($alsoFlag) {
            $attributes['must_change_password'] = false;
        }
        $user->forceFill($attributes)->save();
    }

    /** Remove expired temporary passwords in bulk (scheduled). */
    public function pruneExpired(): int
    {
        return User::query()
            ->whereNotNull('temporary_password_hash')
            ->where('temporary_password_expires_at', '<=', now())
            // Keep the marker while the password itself is the expired temporary one.
            ->whereColumn('temporary_password_hash', '!=', 'password')
            ->update([
                'temporary_password_hash' => null,
                'temporary_password_expires_at' => null,
            ]);
    }
}
