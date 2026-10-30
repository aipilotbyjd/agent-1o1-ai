<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaListAgentRunEventsNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_list_agent_run_events';
    }

    public function name(): string
    {
        return 'Exa: List Research Events';
    }

    public function description(): string
    {
        return 'Lists the steps of a research run.';
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
                'run_id' => Field::text('Run id'),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeList($this->exa($run, $config, 'GET', '/agent/runs/'.$this->pathId($config, 'run_id', 'The run id').'/events', query: $this->pageQuery($config)));
    }
}
