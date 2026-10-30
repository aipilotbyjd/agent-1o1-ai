<?php

namespace App\Nodes\Integrations\Apify;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The trigger registers an Apify webhook for the task (see ApifySource), and the poll is what decides which runs are new.
 */
class ApifyListTaskRunsNode extends AbstractApifyNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'apify_list_task_runs';
    }

    public function name(): string
    {
        return 'Apify: List Task Runs';
    }

    public function description(): string
    {
        return 'Lists a saved task\'s latest runs, newest first. As a trigger it starts the flow when a run finishes.';
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
                'status' => $this->statusField(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->readRuns($run, $config, '/actor-tasks/'.$this->taskId($config).'/runs');
    }

    public function triggerName(): string
    {
        return 'Apify: Task run finished';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a run of an Apify task finishes, or only when it ends a given way. Instant.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'runs';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
