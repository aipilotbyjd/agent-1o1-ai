<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetTaskNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_task';
    }

    public function name(): string
    {
        return 'Apify: Get Task';
    }

    public function description(): string
    {
        return 'Reads a saved task and its settings.';
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
                'task_id' => $this->taskField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['task' => $this->apify($run, $config, 'GET', '/actor-tasks/'.$this->taskId($config))];
    }
}
