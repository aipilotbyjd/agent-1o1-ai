<?php

namespace App\Nodes\Custom;

use App\Contracts\NodeContract;
use App\Models\Nodes\CustomNode;
use App\Models\Runs\Run;
use App\Nodes\DataTransform\CallApiNode;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Services\Secrets\SecretRedactor;
use App\Services\Workflows\TemplateResolver;
use RuntimeException;

/**
 * Executes a workspace-authored `CustomNode` whose `implementation.kind` is
 * `http` — a user-defined API integration, the thing `credential_type` on
 * `custom_nodes` always implied.
 *
 * The `implementation` column holds a request *template*:
 *
 *     {
 *       "kind": "http",
 *       "method": "POST",
 *       "url": "https://api.acme.com/{{ config.resource }}",
 *       "headers": { "Authorization": "Bearer {{ credential.access_token }}" },
 *       "query":   { "page": "{{ config.page }}" },
 *       "body":    { "name": "{{ config.name }}" },
 *       "timeout_seconds": 30
 *     }
 *
 * Two things make this safe to run on behalf of a workspace:
 *
 * 1. **The author and the caller are different people.** The `implementation`
 *    is written once by someone with `NodeManage`; the `config` is supplied per
 *    step by whoever drops the node on a canvas. So the template is resolved
 *    against a context where the *caller's* values live under `config.` and
 *    can only ever land in the positions the author chose. A caller cannot
 *    move the URL or add a header.
 * 2. **The credential never reaches the caller.** It is resolved here, exposed
 *    to the template as `credential.access_token`, and redacted out of this
 *    node's own output before it is returned. The engine's redaction pass
 *    (`WorkflowRunner`/`NodeTester`) covers workspace *Secrets* only — it is
 *    never told about connector credentials — so a node that puts a token on
 *    the wire has to scrub its own response. An endpoint that echoes back the
 *    `Authorization` header it was called with would otherwise write the
 *    plaintext token into `node_runs`, readable by every run-viewer in the
 *    workspace.
 *
 * The request itself is delegated to `CallApiNode` rather than reimplemented,
 * so custom nodes and the built-in Call API node share one HTTP path.
 */
class CustomHttpNode implements NodeContract
{
    use ResolvesConnectorCredential;

    public const string KIND = 'http';

    public function __construct(
        private readonly CustomNode $node,
        private readonly TemplateResolver $templateResolver,
        private readonly CallApiNode $callApi,
        private readonly SecretRedactor $redactor,
    ) {}

    public function type(): string
    {
        return $this->node->nodeType();
    }

    public function category(): string
    {
        return $this->node->category?->slug ?? 'custom';
    }

    public function name(): string
    {
        return $this->node->name;
    }

    public function description(): string
    {
        return $this->node->description ?? '';
    }

    /**
     * The author's own `config_schema` — this is exactly what makes a custom
     * node feel like a built-in one in the picker and under
     * `ConfigSchemaValidator`.
     */
    public function configSchema(): array
    {
        return $this->node->config_schema ?? ['type' => 'object'];
    }

    /**
     * The author's declared `output_schema` when there is one. Without it the
     * node returns whatever `CallApiNode` returns, so that is the honest
     * fallback rather than an empty object.
     */
    public function outputSchema(array $config = []): array
    {
        return $this->node->output_schema ?? $this->callApi->outputSchema();
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->assertBelongsToRunWorkspace($run);

        $implementation = $this->node->implementation ?? [];

        if (($implementation['kind'] ?? null) !== self::KIND) {
            throw new RuntimeException(
                "Custom node [{$this->type()}] has no runnable HTTP implementation."
            );
        }

        $credential = $this->credential($run, $config);

        $request = $this->templateResolver->resolve(
            [
                'method' => $implementation['method'] ?? 'GET',
                'url' => $implementation['url'] ?? null,
                'headers' => $implementation['headers'] ?? [],
                'query' => $implementation['query'] ?? [],
                'body' => $implementation['body'] ?? [],
            ],
            [
                ...$context,
                'config' => $config,
                ...($credential === null ? [] : ['credential' => $credential]),
            ],
        );

        if (! is_string($request['url']) || $request['url'] === '') {
            throw new RuntimeException("Custom node [{$this->type()}] resolved to an empty URL.");
        }

        $output = $this->callApi->execute($run, [
            'method' => strtoupper((string) $request['method']),
            'url' => $this->withQuery($request['url'], $request['query']),
            'headers' => $this->stringMap($request['headers']),
            'body' => is_array($request['body']) ? $request['body'] : [],
            'timeout_seconds' => (int) ($implementation['timeout_seconds'] ?? 30),
        ], $context);

        return $this->redactor->redact($output, array_values($credential ?? []));
    }

    /**
     * `NodeRegistry` already scopes the `custom:{id}` lookup to a workspace,
     * so reaching this check means something bypassed it. It stays because the
     * consequence of being wrong here is one workspace's run driving another
     * workspace's integration — cheap to assert, expensive to miss.
     */
    private function assertBelongsToRunWorkspace(Run $run): void
    {
        if ($this->node->workspace_id !== $run->workspace_id) {
            throw new RuntimeException("Custom node [{$this->type()}] does not belong to this workspace.");
        }
    }

    /**
     * The credential this node's author declared, resolved for the workspace
     * running it — or null when the node declares no `credential_type`.
     *
     * Null rather than an empty array so an author who templates
     * `{{ credential.access_token }}` on a node with no declared credential
     * sees it fail to resolve, instead of silently sending `Bearer `.
     *
     * @param  array<string, mixed>  $config
     * @return array{access_token: string}|null
     */
    private function credential(Run $run, array $config): ?array
    {
        if ($this->node->credential_type === null) {
            return null;
        }

        return ['access_token' => $this->resolveAccessToken($run, $config)];
    }

    /**
     * @param  mixed  $query
     */
    private function withQuery(string $url, $query): string
    {
        $pairs = $this->stringMap($query);

        if ($pairs === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($pairs);
    }

    /**
     * Headers and query strings are flat string maps; a template that resolved
     * to an array or null would otherwise reach Guzzle as an unusable value.
     *
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            if ($item === null || is_array($item)) {
                continue;
            }

            $map[(string) $key] = is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;
        }

        return $map;
    }
}
