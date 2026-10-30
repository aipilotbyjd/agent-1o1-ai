<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyUpdateTaskNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_update_task';
    }

    public function name(): string
    {
        return 'Apify: Update Task';
    }

    public function description(): string
    {
        return 'Changes a saved task\'s name, input or settings. Empty settings stay as they are.';
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
                'task_id' => $this->taskField(),
                'name' => Field::text('Name'),
                ...$this->taskFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['task' => $this->apify($run, $config, 'PUT', '/actor-tasks/'.$this->taskId($config), $this->taskBody($config))];
    }
}
