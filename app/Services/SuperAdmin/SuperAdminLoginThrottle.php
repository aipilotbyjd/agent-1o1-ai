<?php

namespace App\Services\SuperAdmin;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Per-account lockout for the super admin panel's password form, with its own
 * cache keys so customer lockouts and super admin lockouts never mix, even
 * when the same address exists in both. Failures are counted for addresses
 * that don't resolve to an account too, so a lockout never reveals which
 * addresses have one. Thresholds and the backoff ladder come from
 * `auth.lockout`, shared with the customer sign-in.
 */
class SuperAdminLoginThrottle
{
    public function assertNotLocked(string $email): void
    {
        $lockedUntil = Cache::get($this->key('locked', $email));

        if ($lockedUntil === null) {
            return;
        }

        $seconds = max(1, $lockedUntil - now()->getTimestamp());

        throw new TooManyRequestsHttpException(
            $seconds,
            'Too many failed sign-in attempts. Try again in '.$this->humanize($seconds).'.',
        );
    }

    /**
     * @return int|null Seconds the account was just locked for, or null if this
     *                  failure did not trip the threshold.
     */
    public function recordFailure(string $email): ?int
    {
        $attempts = (int) Cache::get($this->key('attempts', $email), 0) + 1;

        Cache::put(
            $this->key('attempts', $email),
            $attempts,
            now()->addMinutes((int) config('auth.lockout.attempt_window_minutes')),
        );

        if ($attempts < (int) config('auth.lockout.max_attempts')) {
            return null;
        }

        return $this->lock($email);
    }

    public function clear(string $email): void
    {
        Cache::forget($this->key('attempts', $email));
        Cache::forget($this->key('locked', $email));
    }

    /**
     * @return int Seconds locked.
     */
    private function lock(string $email): int
    {
        $priorLockouts = (int) Cache::get($this->key('lockouts', $email), 0);
        Cache::put(
            $this->key('lockouts', $email),
            $priorLockouts + 1,
            now()->addHours((int) config('auth.lockout.escalation_window_hours')),
        );

        /** @var array<int, int> $backoff */
        $backoff = config('auth.lockout.backoff');
        $seconds = $backoff[min($priorLockouts, count($backoff) - 1)] * 60;

        Cache::put($this->key('locked', $email), now()->getTimestamp() + $seconds, now()->addSeconds($seconds));
        Cache::forget($this->key('attempts', $email));

        return $seconds;
    }

    private function humanize(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' '.str('second')->plural($seconds);
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes.' '.str('minute')->plural($minutes);
    }

    private function key(string $kind, string $email): string
    {
        return "super-admin-auth-throttle:{$kind}:".hash('sha256', mb_strtolower(trim($email)));
    }
}
