<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyUpdateTaskInputNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_update_task_input';
    }

    public function name(): string
    {
        return 'Apify: Update Task Input';
    }

    public function description(): string
    {
        return 'Replaces the input a saved task runs with.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id', 'input'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => $this->taskField(),
                'input' => Field::json('Input'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['input' => $this->apifyResponse($run, $config, 'PUT', '/actor-tasks/'.$this->taskId($config).'/input', $this->decoded($config['input'] ?? null, 'The input') ?? [])->json()];
    }
}
