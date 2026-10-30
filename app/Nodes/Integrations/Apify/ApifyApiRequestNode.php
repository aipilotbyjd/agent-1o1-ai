<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyApiRequestNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_api_request';
    }

    public function name(): string
    {
        return 'Apify: Make an API Call';
    }

    public function description(): string
    {
        return 'Calls any Apify v2 address with the connected account, for what the other nodes don\'t cover.';
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
                ...$this->apiRequestFields('/users/me'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $method = $this->apiMethod($config);

        return ['response' => $this->apify($run, $config, $method, $this->apiPath($config), $method === 'GET' ? null : ($this->decoded($config['body'] ?? null, 'The body') ?? []), (array) ($this->decoded($config['query'] ?? null, 'The query') ?? []), 60)];
    }
}
