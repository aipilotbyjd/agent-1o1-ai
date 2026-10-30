<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaGetMonitorNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_monitor';
    }

    public function name(): string
    {
        return 'Exa: Get Monitor';
    }

    public function description(): string
    {
        return 'Reads a search monitor.';
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
        return ['monitor' => $this->exa($run, $config, 'GET', '/monitors/'.$this->pathId($config, 'monitor_id', 'The monitor'))];
    }
}
