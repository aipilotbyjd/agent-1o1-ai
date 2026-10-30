<?php

namespace App\Nodes\Integrations\Tavily;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TavilyMapNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_map';
    }

    public function name(): string
    {
        return 'Tavily: Map';
    }

    public function description(): string
    {
        return 'Lists a website\'s addresses from a starting address, without reading their content.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->walkFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->tavily($run, $config, 'POST', '/map', [...$this->walkBody($config), 'include_usage' => true], $this->timeoutSeconds($config) + 15);
        $results = array_values((array) ($result['results'] ?? []));

        return ['base_url' => $result['base_url'] ?? null, 'urls' => $results, 'count' => count($results), 'credits' => data_get($result, 'usage.credits'), 'request_id' => $result['request_id'] ?? null];
    }
}
