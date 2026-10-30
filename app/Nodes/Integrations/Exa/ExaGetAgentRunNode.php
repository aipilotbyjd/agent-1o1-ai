<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaGetAgentRunNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_agent_run';
    }

    public function name(): string
    {
        return 'Exa: Get Research';
    }

    public function description(): string
    {
        return 'Reads how a research run is going, and its results once done.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['run_id'],
            'properties' => [
                ...$this->credentialFields(),
                'run_id' => Field::text('Run id', 'The id Start Research returned.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeAgentRun($this->exa($run, $config, 'GET', '/agent/runs/'.$this->pathId($config, 'run_id', 'The run id')));
    }
}
