<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Auth\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backstop for suspended accounts on the API. Suspending revokes every
 * token, so this only ever catches a token that slipped through — but it
 * means no route has to remember to check.
 *
 * Only looks at requests that carry a Passport token; anonymous and
 * API-key routes are untouched.
 */
class EnsureUserIsNotSuspended
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->carriesPassportToken($request)) {
            $user = $request->user('api');

            if ($user instanceof User && $user->isSuspended()) {
                return ApiResponse::forbidden(AuthService::SUSPENDED_MESSAGE);
            }
        }

        return $next($request);
    }

    /**
     * Passport access tokens are JWTs. Public API keys (`ak_…`) travel as
     * bearer tokens too, and must not be handed to the Passport guard.
     */
    private function carriesPassportToken(Request $request): bool
    {
        $token = $request->bearerToken();

        return $token !== null && substr_count($token, '.') === 2;
    }
}
