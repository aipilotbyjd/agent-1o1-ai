<?php

namespace App\Nodes\Integrations\Tavily;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TavilyApiRequestNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_api_request';
    }

    public function name(): string
    {
        return 'Tavily: Make an API Call';
    }

    public function description(): string
    {
        return 'Calls any Tavily address with the connected account, for what the other nodes don\'t cover.';
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
                ...$this->apiRequestFields('/usage'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $method = $this->apiMethod($config);

        return ['response' => $this->tavily($run, $config, $method, $this->apiPath($config), $method === 'GET' ? null : ($this->decoded($config['body'] ?? null, 'The body') ?? []), 60, (array) ($this->decoded($config['query'] ?? null, 'The query') ?? []))];
    }
}
