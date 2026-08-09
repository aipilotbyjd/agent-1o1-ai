<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\NodeContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the Slack node family
 * (docs/NODES_CATALOG.md's "Priority 1" list). `access_token` is a plain
 * bearer-token config field for now, not a `ConnectorCredential` — OAuth/
 * encrypted credential storage is docs/PLAN.md Phase 6, not yet built.
 * Until then, a workspace member sets a Slack bot token directly in the
 * node's config (or binds it via `AgentToolBinding.config` when the node is
 * attached to an Agent as a tool, keeping it hidden from the model exactly
 * like any other bound field — see docs/AGENTS_PLAN.md's tool-binding
 * security boundary).
 */
abstract class AbstractSlackNode implements NodeContract
{
    private const string BASE_URL = 'https://slack.com/api/';

    public function category(): string
    {
        return 'slack';
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function requiredAccessToken(array $config): string
    {
        $token = $config['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Slack access_token is required.');
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
     * Slack's API always answers 200 and signals failure via a body-level
     * `ok: false` + `error` code, not an HTTP status — so a normal
     * `$response->throw()` would never catch a Slack-side failure.
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

        $body = $response->json() ?? [];

        if (($body['ok'] ?? false) !== true) {
            throw new RuntimeException('Slack API error ['.$endpoint.']: '.($body['error'] ?? 'unknown_error'));
        }

        return $body;
    }
}
