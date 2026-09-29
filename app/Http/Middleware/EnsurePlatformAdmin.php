<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the `/v1/admin/*` routes: the caller must be a platform admin (set
 * only by `php artisan admin:grant`) and, unless switched off in
 * `config/platform_admin.php`, must have two-factor authentication
 * confirmed — these routes can grant credits and plan time to anyone.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isPlatformAdmin(), 403, 'This action is restricted to platform admins.');

        abort_if(
            config('platform_admin.require_two_factor') && ! $user->hasTwoFactorEnabled(),
            403,
            'Enable two-factor authentication to use the admin API.',
        );

        return $next($request);
    }
}
