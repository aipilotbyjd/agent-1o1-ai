<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetTaskNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_task';
    }

    public function name(): string
    {
        return 'Apollo: Get Task';
    }

    public function description(): string
    {
        return 'Reads one task by its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['task' => $this->shapeTask((array) ($this->apollo($run, $config, 'GET', '/tasks/'.$this->recordId($config, 'task_id', 'The task id'))['task'] ?? []))];
    }
}
