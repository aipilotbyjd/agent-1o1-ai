<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyCreateTaskNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_create_task';
    }

    public function name(): string
    {
        return 'Apify: Create Task';
    }

    public function description(): string
    {
        return 'Saves an Actor with a chosen input and settings as a reusable task.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['actor_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'actor_id' => $this->actorField(),
                'name' => Field::text('Name', 'Lower case letters, digits and -.', 'my-task'),
                ...$this->taskFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['task' => $this->apify($run, $config, 'POST', '/actor-tasks', ['actId' => $this->actorId($config), ...$this->taskBody($config)])];
    }
}
