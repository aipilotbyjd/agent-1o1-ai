<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FirecrawlGetMonitorNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_monitor';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Monitor';
    }

    public function description(): string
    {
        return 'Reads a monitor\'s settings.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['monitor' => $this->firecrawl($run, $config, 'GET', '/monitor/'.$this->pathId($config, 'monitor_id', 'The monitor'))['data'] ?? []];
    }
}
