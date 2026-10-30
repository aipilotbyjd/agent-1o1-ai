<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaGetMonitorRunNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_monitor_run';
    }

    public function name(): string
    {
        return 'Exa: Get Monitor Run';
    }

    public function description(): string
    {
        return 'Reads one run of a search monitor, with what it found.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id', 'run_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
                'run_id' => Field::text('Run id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['run' => $this->exa($run, $config, 'GET', '/monitors/'.$this->pathId($config, 'monitor_id', 'The monitor').'/runs/'.$this->pathId($config, 'run_id', 'The run id'))];
    }
}
