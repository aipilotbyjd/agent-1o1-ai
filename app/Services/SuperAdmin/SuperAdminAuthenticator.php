<?php

namespace App\Services\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use App\Notifications\SuperAdmin\SuperAdminSignedInNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * The super admin panel's sign-in flow. A correct password never signs anyone in
 * on its own: it only opens a short-lived "pending" state in the session,
 * and the `super_admin` guard is logged in after the second factor — or, for an
 * account that has none yet, after enrollment is confirmed. There is no
 * moment where a session is authenticated without two-factor.
 *
 * Wrong passwords count against the account through `SuperAdminLoginThrottle` (own
 * cache keys, so customer lockouts and super admin lockouts never mix),
 * and unknown addresses are hashed and counted the same way so neither the
 * response nor its timing reveals which addresses have accounts.
 */
class SuperAdminAuthenticator
{
    public const string PENDING_ID = 'super_admin.pending.id';

    public const string PENDING_EXPIRES_AT = 'super_admin.pending.expires_at';

    public const string PENDING_ATTEMPTS = 'super_admin.pending.attempts';

    public const string SIGNED_IN_AT = 'super_admin.auth.signed_in_at';

    public const string LAST_ACTIVITY_AT = 'super_admin.auth.last_activity_at';

    public const string PASSWORD_FINGERPRINT = 'super_admin.auth.password_fingerprint';

    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly SuperAdminLoginThrottle $throttle,
    ) {}

    /**
     * Checks the password and, if it is right, opens the pending state.
     *
     * @throws TooManyRequestsHttpException while the account is locked
     */
    public function attempt(Request $request, string $email, string $password): ?SuperAdmin
    {
        $this->throttle->assertNotLocked($email);

        $superAdmin = SuperAdmin::query()->where('email', $email)->first();

        $passwordMatches = $superAdmin !== null
            ? Hash::check($password, $superAdmin->password)
            : Hash::check($password, Hash::make(Str::random(40)));

        if (! $passwordMatches || $superAdmin === null || $superAdmin->isDisabled()) {
            $lockedFor = $this->throttle->recordFailure($email);

            $this->audit->record($superAdmin, 'super_admin.login.failed', $superAdmin, null, array_filter([
                'email' => $superAdmin === null ? Str::limit($email, 191, '') : null,
                'reason' => $superAdmin?->isDisabled() ? 'disabled' : 'credentials',
                'locked_for_seconds' => $lockedFor,
            ]));

            return null;
        }

        $this->throttle->clear($email);

        $request->session()->regenerate();
        $request->session()->put([
            self::PENDING_ID => $superAdmin->getKey(),
            self::PENDING_EXPIRES_AT => now()->addMinutes((int) config('super_admin.challenge_timeout'))->getTimestamp(),
            self::PENDING_ATTEMPTS => 0,
        ]);

        return $superAdmin;
    }

    /**
     * The super admin whose password was just accepted, while the pending state is
     * still fresh and the account still usable.
     */
    public function pendingSuperAdmin(Request $request): ?SuperAdmin
    {
        $session = $request->session();
        $id = $session->get(self::PENDING_ID);

        if ($id === null || (int) $session->get(self::PENDING_EXPIRES_AT) < now()->getTimestamp()) {
            $this->forgetPending($request);

            return null;
        }

        $superAdmin = SuperAdmin::query()->find($id);

        if ($superAdmin === null || $superAdmin->isDisabled()) {
            $this->forgetPending($request);

            return null;
        }

        return $superAdmin;
    }

    /**
     * Counts a wrong second-factor code. Returns false once the pending state
     * has been thrown away and the password must be entered again.
     */
    public function recordFailedChallenge(Request $request, SuperAdmin $superAdmin, string $action): bool
    {
        $attempts = (int) $request->session()->get(self::PENDING_ATTEMPTS) + 1;

        $this->audit->record($superAdmin, $action, $superAdmin, null, ['attempt' => $attempts]);

        if ($attempts >= (int) config('super_admin.max_challenge_attempts')) {
            $this->forgetPending($request);

            return false;
        }

        $request->session()->put(self::PENDING_ATTEMPTS, $attempts);

        return true;
    }

    /**
     * Signs the super admin in after the second factor (or enrollment) succeeded.
     */
    public function completeLogin(Request $request, SuperAdmin $superAdmin, string $method): void
    {
        $this->forgetPending($request);

        Auth::guard('super_admin')->login($superAdmin);

        $request->session()->regenerate();
        $request->session()->put([
            self::SIGNED_IN_AT => now()->getTimestamp(),
            self::LAST_ACTIVITY_AT => now()->getTimestamp(),
            self::PASSWORD_FINGERPRINT => $this->passwordFingerprint($superAdmin),
        ]);

        $superAdmin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->audit->record($superAdmin, 'super_admin.login.succeeded', $superAdmin, null, ['method' => $method]);

        $superAdmin->notify(new SuperAdminSignedInNotification(
            (string) $request->ip(),
            Str::limit((string) $request->userAgent(), 255),
            now()->toImmutable(),
        ));
    }

    /**
     * Ties the session to the password it was opened with, so changing or
     * resetting the password ends every other session.
     */
    public function passwordFingerprint(SuperAdmin $superAdmin): string
    {
        return hash_hmac('sha256', $superAdmin->password, (string) config('app.key'));
    }

    public function refreshPasswordFingerprint(Request $request, SuperAdmin $superAdmin): void
    {
        $request->session()->put(self::PASSWORD_FINGERPRINT, $this->passwordFingerprint($superAdmin));
    }

    public function logout(Request $request, ?string $reason = null): void
    {
        $superAdmin = Auth::guard('super_admin')->user();

        Auth::guard('super_admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($superAdmin instanceof SuperAdmin) {
            $this->audit->record($superAdmin, 'super_admin.logout', $superAdmin, null, $reason !== null ? ['reason' => $reason] : null);
        }
    }

    private function forgetPending(Request $request): void
    {
        $request->session()->forget([self::PENDING_ID, self::PENDING_EXPIRES_AT, self::PENDING_ATTEMPTS]);
    }
}
