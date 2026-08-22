<?php

namespace App\Nodes\Integrations\Gmail;

use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the Gmail node family
 * (docs/NODES_CATALOG.md's "Priority 1" list). Resolves the access token via
 * `ResolvesConnectorCredential` — a workspace-scoped `ConnectorCredential`
 * referenced by `config['credential_id']`, or a plain `access_token` config
 * field for nodes configured before `ConnectorCredential` existed.
 */
abstract class AbstractGmailNode implements NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://gmail.googleapis.com/gmail/v1';

    /**
     * Gmail's response bodies are returned verbatim by most of this family —
     * see `AbstractSlackNode::outputSchema()` for why that is declared as a
     * free-form object rather than transcribed key by key.
     */
    public function outputSchema(array $config = []): array
    {
        return ['type' => 'object'];
    }

    public function category(): string
    {
        return 'gmail';
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
     * Unlike Slack, Gmail signals failure via a normal HTTP status code, with
     * the error detail under `error.message` in the JSON body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(Run $run, string $method, string $endpoint, array $config, array $params): array
    {
        $token = $this->resolveAccessToken($run, $config);

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
