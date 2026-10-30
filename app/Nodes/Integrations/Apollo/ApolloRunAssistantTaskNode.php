<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloRunAssistantTaskNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_run_assistant_task';
    }

    public function name(): string
    {
        return 'Apollo: Run Assistant Task';
    }

    public function description(): string
    {
        return 'Gives Apollo\'s own assistant an instruction, or answers it. It works in the background: read the result with Poll Assistant Task. What it does can use credits.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['instruction'],
            'properties' => [
                ...$this->credentialFields(),
                'instruction' => Field::textarea('Instruction'),
                'task_id' => Field::advanced(Field::text('Continue task id', 'To answer or continue an earlier task.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->apollo($run, $config, 'POST', '/agents/task', [], ['instruction' => $this->requiredString($config, 'instruction', 'The instruction'), 'task_id' => filled($config['task_id'] ?? null) ? $this->recordId($config, 'task_id', 'The task id') : null]);
    }
}
