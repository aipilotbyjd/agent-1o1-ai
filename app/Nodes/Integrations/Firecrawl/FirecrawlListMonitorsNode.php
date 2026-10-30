<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlListMonitorsNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_list_monitors';
    }

    public function name(): string
    {
        return 'Firecrawl: List Monitors';
    }

    public function description(): string
    {
        return 'Lists the team\'s monitors.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
                'offset' => Field::advanced(Field::integer('Skip', null, null, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $monitors = array_values((array) ($this->firecrawl($run, $config, 'GET', '/monitor', query: ['limit' => $this->limit($config, 50, 100), 'offset' => $this->intOf($config, 'offset')])['data'] ?? []));

        return ['monitors' => $monitors, 'count' => count($monitors)];
    }
}
