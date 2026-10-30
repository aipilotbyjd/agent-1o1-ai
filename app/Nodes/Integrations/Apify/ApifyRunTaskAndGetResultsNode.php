<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyRunTaskAndGetResultsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_run_task_and_get_results';
    }

    public function name(): string
    {
        return 'Apify: Run Task And Get Results';
    }

    public function description(): string
    {
        return 'Runs a saved task, waits up to a minute, and returns its results. A longer run is handed back by id, with no results yet.';
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
                ...$this->runAndReadFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->runAndRead($run, $config, '/actor-tasks/'.$this->taskId($config).'/runs');
    }
}
