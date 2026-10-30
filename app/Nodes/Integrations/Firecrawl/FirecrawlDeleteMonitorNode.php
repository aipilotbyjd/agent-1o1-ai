<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FirecrawlDeleteMonitorNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_delete_monitor';
    }

    public function name(): string
    {
        return 'Firecrawl: Delete Monitor';
    }

    public function description(): string
    {
        return 'Deletes a monitor and its history for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->firecrawl($run, $config, 'DELETE', '/monitor/'.$this->pathId($config, 'monitor_id', 'The monitor'));

        return ['deleted' => true];
    }
}
