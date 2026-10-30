<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloPollAssistantTaskNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_poll_assistant_task';
    }

    public function name(): string
    {
        return 'Apollo: Poll Assistant Task';
    }

    public function description(): string
    {
        return 'Reads the status and messages of an assistant task.';
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
        return $this->apollo($run, $config, 'POST', '/agents/poll_task', [], ['task_id' => $this->recordId($config, 'task_id', 'The task id')]);
    }
}
