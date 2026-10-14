<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the admin panel its own session cookie, set before `StartSession`
 * runs: a different name from the main app's, host-only (no shared parent
 * domain), HttpOnly, SameSite=Strict, Secure outside local development, and
 * an encrypted payload. A main-app cookie is never read here and an admin
 * cookie is never sent anywhere else.
 */
class ConfigureAdminSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'session.cookie' => config('admin_panel.session_cookie'),
            'session.domain' => null,
            'session.path' => '/',
            'session.secure' => app()->isLocal() || app()->runningUnitTests() ? $request->isSecure() : true,
            'session.http_only' => true,
            'session.same_site' => 'strict',
            'session.encrypt' => true,
            'session.expire_on_close' => true,
            'session.lifetime' => (int) config('admin_panel.idle_timeout'),
        ]);

        // Renamed rather than rebuilt: a guard may already hold this store.
        app('session')->driver()->setName((string) config('admin_panel.session_cookie'));

        return $next($request);
    }
}
