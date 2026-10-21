<?php

namespace App\Http\Middleware\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\SuperAdminAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks a signed-in admin on every request, after `auth:super_admin`. The
 * session ends when the account is disabled, its two-factor is reset, its
 * password changes elsewhere, it sits idle past `super_admin.idle_timeout`, or it
 * outlives `super_admin.absolute_lifetime`.
 */
class EnsureSuperAdminSessionIsValid
{
    public function __construct(private readonly SuperAdminAuthenticator $authenticator) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Read fresh: a disable, 2FA reset or password change must apply now.
        $superAdmin = SuperAdmin::query()->find(Auth::guard('super_admin')->id());
        $reason = $this->invalidReason($request, $superAdmin);

        if ($reason !== null) {
            $this->authenticator->logout($request, $reason);

            return redirect()->route('super-admin.login')->with('status', $reason === 'idle' || $reason === 'expired'
                ? 'Your session expired. Sign in again.'
                : 'You have been signed out. Sign in again.');
        }

        $request->session()->put(SuperAdminAuthenticator::LAST_ACTIVITY_AT, now()->getTimestamp());

        return $next($request);
    }

    private function invalidReason(Request $request, mixed $superAdmin): ?string
    {
        if (! $superAdmin instanceof SuperAdmin || $superAdmin->isDisabled()) {
            return 'disabled';
        }

        if (! $superAdmin->hasTwoFactorEnabled()) {
            return 'two_factor_reset';
        }

        $session = $request->session();

        if (! hash_equals($this->authenticator->passwordFingerprint($superAdmin), (string) $session->get(SuperAdminAuthenticator::PASSWORD_FINGERPRINT))) {
            return 'password_changed';
        }

        $now = now()->getTimestamp();

        if ((int) $session->get(SuperAdminAuthenticator::LAST_ACTIVITY_AT) + (int) config('super_admin.idle_timeout') * 60 < $now) {
            return 'idle';
        }

        if ((int) $session->get(SuperAdminAuthenticator::SIGNED_IN_AT) + (int) config('super_admin.absolute_lifetime') * 60 < $now) {
            return 'expired';
        }

        return null;
    }
}
