<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaDeleteAgentRunNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_delete_agent_run';
    }

    public function name(): string
    {
        return 'Exa: Delete Research';
    }

    public function description(): string
    {
        return 'Deletes a stored research run.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->exa($run, $config, 'DELETE', '/agent/runs/'.$this->pathId($config, 'run_id', 'The run id'));

        return ['deleted' => true];
    }
}
