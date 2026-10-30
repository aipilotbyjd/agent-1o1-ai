<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloSkipTaskNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_skip_task';
    }

    public function name(): string
    {
        return 'Apollo: Skip Task';
    }

    public function description(): string
    {
        return 'Marks a task as skipped.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task id'),
                'note' => Field::textarea('Note'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'POST', '/tasks/'.$this->recordId($config, 'task_id', 'The task id').'/skip', [], $this->only($config, ['note']));

        return ['task' => $this->shapeTask((array) ($body['task'] ?? []))];
    }
}
