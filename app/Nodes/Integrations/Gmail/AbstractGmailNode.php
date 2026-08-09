<?php

namespace App\Nodes\Integrations\Gmail;

use App\Contracts\NodeContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the Gmail node family
 * (docs/NODES_CATALOG.md's "Priority 1" list). `access_token` is a plain
 * bearer-token config field for now, not a `ConnectorCredential` — OAuth/
 * encrypted credential storage is docs/PLAN.md Phase 6, not yet built. Same
 * stopgap as `AbstractSlackNode`.
 */
abstract class AbstractGmailNode implements NodeContract
{
    private const string BASE_URL = 'https://gmail.googleapis.com/gmail/v1';

    public function category(): string
    {
        return 'gmail';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function requiredAccessToken(array $config): string
    {
        $token = $config['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Gmail access_token is required.');
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
     * Unlike Slack, Gmail signals failure via a normal HTTP status code, with
     * the error detail under `error.message` in the JSON body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $method, string $endpoint, array $config, array $params): array
    {
        $token = $this->requiredAccessToken($config);

        $request = Http::withToken($token);
        $response = $method === 'get'
            ? $request->get(self::BASE_URL.$endpoint, $params)
            : $request->asJson()->post(self::BASE_URL.$endpoint, $params);

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Gmail API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }
}
