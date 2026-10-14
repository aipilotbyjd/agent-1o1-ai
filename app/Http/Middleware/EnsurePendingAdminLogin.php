<?php

namespace App\Http\Middleware;

use App\Services\Admin\AdminAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only a browser that just entered a correct password reach the
 * two-factor pages, and only within `admin_panel.challenge_timeout`. The pending
 * admin is handed to the controller as the `pending_admin` attribute.
 */
class EnsurePendingAdminLogin
{
    public function __construct(private readonly AdminAuthenticator $authenticator) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $this->authenticator->pendingAdmin($request);

        if ($admin === null) {
            return redirect()->route('panel.login')->with('status', 'Your sign-in expired. Enter your password again.');
        }

        $request->attributes->set('pending_admin', $admin);

        return $next($request);
    }
}
