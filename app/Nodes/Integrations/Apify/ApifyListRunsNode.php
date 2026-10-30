<?php

namespace App\Nodes\Integrations\Apify;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * Apify's webhooks are per Actor or task, so the "any run" trigger polls.
 */
class ApifyListRunsNode extends AbstractApifyNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'apify_list_runs';
    }

    public function name(): string
    {
        return 'Apify: List All Runs';
    }

    public function description(): string
    {
        return 'Lists the latest runs of all your Actors and tasks, newest first. As a trigger it starts the flow when any run finishes. Checked every few minutes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'status' => $this->statusField(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->readRuns($run, $config, '/actor-runs');
    }

    public function triggerName(): string
    {
        return 'Apify: Any run finished';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when any run in the Apify account finishes, or only when it ends a given way. Checked every few minutes.';
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
