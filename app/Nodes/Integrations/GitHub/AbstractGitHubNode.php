<?php

namespace App\Nodes\Integrations\GitHub;

use App\Contracts\NodeContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the GitHub node family
 * (docs/NODES_CATALOG.md's "Priority 1" list). `access_token` is a plain
 * bearer-token config field for now, not a `ConnectorCredential` — OAuth/
 * encrypted credential storage is docs/PLAN.md Phase 6, not yet built. Same
 * stopgap as `AbstractSlackNode`/`AbstractGmailNode`.
 */
abstract class AbstractGitHubNode implements NodeContract
{
    private const string BASE_URL = 'https://api.github.com';

    public function category(): string
    {
        return 'github';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function requiredAccessToken(array $config): string
    {
        $token = $config['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('GitHub access_token is required.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function get(string $endpoint, array $config, array $params = []): array
    {
        return $this->call('get', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function post(string $endpoint, array $config, array $params = []): array
    {
        return $this->call('post', $endpoint, $config, $params);
    }

    /**
     * GitHub signals failure via a normal HTTP status code, with the error
     * detail under a top-level `message` in the JSON body. `null` params
     * (an unset optional filter) are dropped rather than sent through, since
     * an empty query string value is not the same as "omit this filter".
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $method, string $endpoint, array $config, array $params): array
    {
        $token = $this->requiredAccessToken($config);

        $request = Http::withToken($token)->withHeaders(['Accept' => 'application/vnd.github+json']);
        $params = array_filter($params, fn ($value) => $value !== null);

        $response = $method === 'get'
            ? $request->get(self::BASE_URL.$endpoint, $params)
            : $request->post(self::BASE_URL.$endpoint, $params);

        if ($response->failed()) {
            $message = $response->json('message') ?? $response->body();

            throw new RuntimeException("GitHub API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }
}
