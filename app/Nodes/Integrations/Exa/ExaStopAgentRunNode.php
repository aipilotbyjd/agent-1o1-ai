<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaStopAgentRunNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_stop_agent_run';
    }

    public function name(): string
    {
        return 'Exa: Stop Research';
    }

    public function description(): string
    {
        return 'Stops a running research run gracefully, keeping what it found so far.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['run_id'],
            'properties' => [
                ...$this->credentialFields(),
                'run_id' => Field::text('Run id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeAgentRun($this->exa($run, $config, 'POST', '/agent/runs/'.$this->pathId($config, 'run_id', 'The run id').'/stop', []));
    }
}
