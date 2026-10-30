<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaApiRequestNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_api_request';
    }

    public function name(): string
    {
        return 'Exa: Make an API Call';
    }

    public function description(): string
    {
        return 'Calls any Exa address with the connected account, for what the other nodes don\'t cover.';
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
                ...$this->apiRequestFields('/search'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $method = $this->apiMethod($config);

        return ['response' => $this->exa($run, $config, $method, $this->apiPath($config), $method === 'GET' ? null : ($this->decoded($config['body'] ?? null, 'The body') ?? []), (array) ($this->decoded($config['query'] ?? null, 'The query') ?? []), 60)];
    }
}
