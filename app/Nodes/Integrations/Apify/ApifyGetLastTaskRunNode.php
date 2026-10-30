<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetLastTaskRunNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_last_task_run';
    }

    public function name(): string
    {
        return 'Apify: Find Last Task Run';
    }

    public function description(): string
    {
        return 'Finds a saved task\'s latest run, optionally with a given outcome.';
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
                'status' => Field::select('Outcome', ['SUCCEEDED', 'FAILED', 'ABORTED', 'TIMED-OUT', 'RUNNING'], 'Leave empty for the latest of any kind.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeRun($this->apify($run, $config, 'GET', '/actor-tasks/'.$this->taskId($config).'/runs/last', query: ['status' => $this->textOf($config, 'status')]));
    }
}
