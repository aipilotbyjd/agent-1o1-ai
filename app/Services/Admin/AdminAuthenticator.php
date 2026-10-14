<?php

namespace App\Services\Admin;

use App\Models\Admin\Admin;
use App\Notifications\Admin\AdminSignedInNotification;
use App\Services\Auth\LoginThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * The admin panel's sign-in flow. A correct password never signs anyone in
 * on its own: it only opens a short-lived "pending" state in the session,
 * and the `admin` guard is logged in after the second factor — or, for an
 * account that has none yet, after enrollment is confirmed. There is no
 * moment where a session is authenticated without two-factor.
 *
 * Wrong passwords count against the account through `LoginThrottle` (under
 * its own key prefix, so customer lockouts and admin lockouts never mix),
 * and unknown addresses are hashed and counted the same way so neither the
 * response nor its timing reveals which addresses have accounts.
 */
class AdminAuthenticator
{
    public const string PENDING_ID = 'admin.pending.id';

    public const string PENDING_EXPIRES_AT = 'admin.pending.expires_at';

    public const string PENDING_ATTEMPTS = 'admin.pending.attempts';

    public const string SIGNED_IN_AT = 'admin.auth.signed_in_at';

    public const string LAST_ACTIVITY_AT = 'admin.auth.last_activity_at';

    public const string PASSWORD_FINGERPRINT = 'admin.auth.password_fingerprint';

    private const string THROTTLE_PREFIX = 'admin-auth-throttle';

    private readonly LoginThrottle $throttle;

    public function __construct(private readonly AdminAuditLogger $audit)
    {
        $this->throttle = new LoginThrottle(self::THROTTLE_PREFIX);
    }

    /**
     * Checks the password and, if it is right, opens the pending state.
     *
     * @throws TooManyRequestsHttpException while the account is locked
     */
    public function attempt(Request $request, string $email, string $password): ?Admin
    {
        $this->throttle->assertNotLocked($email);

        $admin = Admin::query()->where('email', $email)->first();

        $passwordMatches = $admin !== null
            ? Hash::check($password, $admin->password)
            : Hash::check($password, Hash::make(Str::random(40)));

        if (! $passwordMatches || $admin === null || $admin->isDisabled()) {
            $lockedFor = $this->throttle->recordFailure($email);

            $this->audit->record($admin, 'admin.login.failed', $admin, null, array_filter([
                'email' => $admin === null ? Str::limit($email, 191, '') : null,
                'reason' => $admin?->isDisabled() ? 'disabled' : 'credentials',
                'locked_for_seconds' => $lockedFor,
            ]));

            return null;
        }

        $this->throttle->clear($email);

        $request->session()->regenerate();
        $request->session()->put([
            self::PENDING_ID => $admin->getKey(),
            self::PENDING_EXPIRES_AT => now()->addMinutes((int) config('admin_panel.challenge_timeout'))->getTimestamp(),
            self::PENDING_ATTEMPTS => 0,
        ]);

        return $admin;
    }

    /**
     * The admin whose password was just accepted, while the pending state is
     * still fresh and the account still usable.
     */
    public function pendingAdmin(Request $request): ?Admin
    {
        $session = $request->session();
        $id = $session->get(self::PENDING_ID);

        if ($id === null || (int) $session->get(self::PENDING_EXPIRES_AT) < now()->getTimestamp()) {
            $this->forgetPending($request);

            return null;
        }

        $admin = Admin::query()->find($id);

        if ($admin === null || $admin->isDisabled()) {
            $this->forgetPending($request);

            return null;
        }

        return $admin;
    }

    /**
     * Counts a wrong second-factor code. Returns false once the pending state
     * has been thrown away and the password must be entered again.
     */
    public function recordFailedChallenge(Request $request, Admin $admin, string $action): bool
    {
        $attempts = (int) $request->session()->get(self::PENDING_ATTEMPTS) + 1;

        $this->audit->record($admin, $action, $admin, null, ['attempt' => $attempts]);

        if ($attempts >= (int) config('admin_panel.max_challenge_attempts')) {
            $this->forgetPending($request);

            return false;
        }

        $request->session()->put(self::PENDING_ATTEMPTS, $attempts);

        return true;
    }

    /**
     * Signs the admin in after the second factor (or enrollment) succeeded.
     */
    public function completeLogin(Request $request, Admin $admin, string $method): void
    {
        $this->forgetPending($request);

        Auth::guard('admin')->login($admin);

        $request->session()->regenerate();
        $request->session()->put([
            self::SIGNED_IN_AT => now()->getTimestamp(),
            self::LAST_ACTIVITY_AT => now()->getTimestamp(),
            self::PASSWORD_FINGERPRINT => $this->passwordFingerprint($admin),
        ]);

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->audit->record($admin, 'admin.login.succeeded', $admin, null, ['method' => $method]);

        $admin->notify(new AdminSignedInNotification(
            (string) $request->ip(),
            Str::limit((string) $request->userAgent(), 255),
            now()->toImmutable(),
        ));
    }

    /**
     * Ties the session to the password it was opened with, so changing or
     * resetting the password ends every other session.
     */
    public function passwordFingerprint(Admin $admin): string
    {
        return hash_hmac('sha256', $admin->password, (string) config('app.key'));
    }

    public function refreshPasswordFingerprint(Request $request, Admin $admin): void
    {
        $request->session()->put(self::PASSWORD_FINGERPRINT, $this->passwordFingerprint($admin));
    }

    public function logout(Request $request, ?string $reason = null): void
    {
        $admin = Auth::guard('admin')->user();

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($admin instanceof Admin) {
            $this->audit->record($admin, 'admin.logout', $admin, null, $reason !== null ? ['reason' => $reason] : null);
        }
    }

    private function forgetPending(Request $request): void
    {
        $request->session()->forget([self::PENDING_ID, self::PENDING_EXPIRES_AT, self::PENDING_ATTEMPTS]);
    }
}
