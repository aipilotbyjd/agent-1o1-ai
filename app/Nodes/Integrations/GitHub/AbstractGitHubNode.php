<?php

namespace App\Nodes\Integrations\GitHub;

use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the GitHub node family
 * (docs/NODES_CATALOG.md's "Priority 1" list). Resolves the access token via
 * `ResolvesConnectorCredential` — a workspace-scoped `ConnectorCredential`
 * referenced by `config['credential_id']`, or a plain `access_token` config
 * field for nodes configured before `ConnectorCredential` existed.
 */
abstract class AbstractGitHubNode implements NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://api.github.com';

    public function category(): string
    {
        return 'github';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function get(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'get', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function post(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'post', $endpoint, $config, $params);
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
    private function call(Run $run, string $method, string $endpoint, array $config, array $params): array
    {
        $token = $this->resolveAccessToken($run, $config);

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
