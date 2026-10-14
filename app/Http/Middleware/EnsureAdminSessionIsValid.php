<?php

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use App\Services\Admin\AdminAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks a signed-in admin on every request, after `auth:admin`. The
 * session ends when the account is disabled, its two-factor is reset, its
 * password changes elsewhere, it sits idle past `admin_panel.idle_timeout`, or it
 * outlives `admin_panel.absolute_lifetime`.
 */
class EnsureAdminSessionIsValid
{
    public function __construct(private readonly AdminAuthenticator $authenticator) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Read fresh: a disable, 2FA reset or password change must apply now.
        $admin = Admin::query()->find(Auth::guard('admin')->id());
        $reason = $this->invalidReason($request, $admin);

        if ($reason !== null) {
            $this->authenticator->logout($request, $reason);

            return redirect()->route('panel.login')->with('status', $reason === 'idle' || $reason === 'expired'
                ? 'Your session expired. Sign in again.'
                : 'You have been signed out. Sign in again.');
        }

        $request->session()->put(AdminAuthenticator::LAST_ACTIVITY_AT, now()->getTimestamp());

        return $next($request);
    }

    private function invalidReason(Request $request, mixed $admin): ?string
    {
        if (! $admin instanceof Admin || $admin->isDisabled()) {
            return 'disabled';
        }

        if (! $admin->hasTwoFactorEnabled()) {
            return 'two_factor_reset';
        }

        $session = $request->session();

        if (! hash_equals($this->authenticator->passwordFingerprint($admin), (string) $session->get(AdminAuthenticator::PASSWORD_FINGERPRINT))) {
            return 'password_changed';
        }

        $now = now()->getTimestamp();

        if ((int) $session->get(AdminAuthenticator::LAST_ACTIVITY_AT) + (int) config('admin_panel.idle_timeout') * 60 < $now) {
            return 'idle';
        }

        if ((int) $session->get(AdminAuthenticator::SIGNED_IN_AT) + (int) config('admin_panel.absolute_lifetime') * 60 < $now) {
            return 'expired';
        }

        return null;
    }
}
