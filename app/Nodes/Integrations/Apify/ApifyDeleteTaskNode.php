<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyDeleteTaskNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_delete_task';
    }

    public function name(): string
    {
        return 'Apify: Delete Task';
    }

    public function description(): string
    {
        return 'Deletes a saved task for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => $this->taskField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->apify($run, $config, 'DELETE', '/actor-tasks/'.$this->taskId($config));

        return ['deleted' => true];
    }
}
