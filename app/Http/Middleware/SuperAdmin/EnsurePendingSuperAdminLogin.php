<?php

namespace App\Http\Middleware\SuperAdmin;

use App\Services\SuperAdmin\SuperAdminAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only a browser that just entered a correct password reach the
 * two-factor pages, and only within `super_admin.challenge_timeout`. The pending
 * admin is handed to the controller as the `pending_super_admin` attribute.
 */
class EnsurePendingSuperAdminLogin
{
    public function __construct(private readonly SuperAdminAuthenticator $authenticator) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $superAdmin = $this->authenticator->pendingSuperAdmin($request);

        if ($superAdmin === null) {
            return redirect()->route('super-admin.login')->with('status', 'Your sign-in expired. Enter your password again.');
        }

        $request->attributes->set('pending_super_admin', $superAdmin);

        return $next($request);
    }
}
