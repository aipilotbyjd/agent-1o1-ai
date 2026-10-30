<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PerplexityApiRequestNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_api_request';
    }

    public function name(): string
    {
        return 'Perplexity: Make an API Call';
    }

    public function description(): string
    {
        return 'Calls any Perplexity address with the connected account, for what the other nodes don\'t cover.';
    }

    public function effect(array $config): ActionEffect
    {
        return match (strtoupper((string) ($config['method'] ?? 'GET'))) {
            'GET' => ActionEffect::Read,
            'DELETE' => ActionEffect::Destructive,
            default => ActionEffect::Write,
        };
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['path'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->apiRequestFields('/v1/models'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $method = $this->apiMethod($config);

        return ['response' => $this->perplexity($run, $config, $method, $this->apiPath($config), $method === 'GET' ? null : ($this->decoded($config['body'] ?? null, 'The body') ?? []), (array) ($this->decoded($config['query'] ?? null, 'The query') ?? []), 60)];
    }
}
