<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExaTriggerMonitorNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_trigger_monitor';
    }

    public function name(): string
    {
        return 'Exa: Run Monitor Now';
    }

    public function description(): string
    {
        return 'Runs a search monitor right away, outside its schedule.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        return ['run' => $this->exa($run, $config, 'POST', '/monitors/'.$this->pathId($config, 'monitor_id', 'The monitor').'/trigger', [])];
    }
}
