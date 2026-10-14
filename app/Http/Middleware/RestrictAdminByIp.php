<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides the admin panel from addresses outside `admin_panel.allowed_ips`. Answers
 * 404 rather than 403 so the panel's existence isn't confirmed.
 */
class RestrictAdminByIp
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $allowed */
        $allowed = config('admin_panel.allowed_ips');

        abort_if($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed), 404);

        return $next($request);
    }
}
