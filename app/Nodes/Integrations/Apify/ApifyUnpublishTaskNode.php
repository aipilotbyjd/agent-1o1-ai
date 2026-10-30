<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyUnpublishTaskNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_unpublish_task';
    }

    public function name(): string
    {
        return 'Apify: Unpublish Task';
    }

    public function description(): string
    {
        return 'Takes a saved task off its public landing page.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['task' => $this->apify($run, $config, 'PUT', '/actor-tasks/'.$this->taskId($config), ['isPublic' => false])];
    }
}
