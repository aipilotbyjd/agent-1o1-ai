<?php

namespace App\Http\Middleware;

use App\Enums\Auth\ApiKeyAbility;
use App\Models\Auth\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyIsValid
{
    /**
     * Handle an incoming request. Optionally guards a specific ability, e.g.
     * ->middleware('api-key:workflows:read')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $plainTextKey = $request->bearerToken();

        abort_if($plainTextKey === null, 401, 'Missing API key.');

        $apiKey = ApiKey::query()->where('hashed_key', ApiKey::hash($plainTextKey))->first();

        abort_if($apiKey === null, 401, 'Invalid API key.');
        abort_if($apiKey->isExpired(), 401, 'This API key has expired.');

        if ($ability !== null) {
            abort_unless($apiKey->hasAbility(ApiKeyAbility::from($ability)), 403, 'This API key is missing the required ability.');
        }

        $apiKey->update(['last_used_at' => now()]);

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('workspace', $apiKey->workspace);

        return $next($request);
    }
}
