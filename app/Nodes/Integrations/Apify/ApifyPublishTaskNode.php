<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyPublishTaskNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_publish_task';
    }

    public function name(): string
    {
        return 'Apify: Publish Task';
    }

    public function description(): string
    {
        return 'Puts a saved task on its public landing page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
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
        return ['task' => $this->apify($run, $config, 'PUT', '/actor-tasks/'.$this->taskId($config), ['isPublic' => true])];
    }
}
