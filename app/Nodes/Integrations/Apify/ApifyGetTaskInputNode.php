<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetTaskInputNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_task_input';
    }

    public function name(): string
    {
        return 'Apify: Get Task Input';
    }

    public function description(): string
    {
        return 'Reads the input a saved task runs with.';
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
        return ['input' => $this->apifyResponse($run, $config, 'GET', '/actor-tasks/'.$this->taskId($config).'/input')->json()];
    }
}
