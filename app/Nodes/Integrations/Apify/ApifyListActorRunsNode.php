<?php

namespace App\Nodes\Integrations\Apify;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The trigger registers an Apify webhook for the Actor (see ApifySource), and the poll is what decides which runs are new.
 */
class ApifyListActorRunsNode extends AbstractApifyNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'apify_list_actor_runs';
    }

    public function name(): string
    {
        return 'Apify: List Actor Runs';
    }

    public function description(): string
    {
        return 'Lists an Actor\'s latest runs, newest first. As a trigger it starts the flow when a run finishes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['actor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'actor_id' => $this->actorField(),
                'status' => $this->statusField(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->readRuns($run, $config, '/actors/'.$this->actorId($config).'/runs');
    }

    public function triggerName(): string
    {
        return 'Apify: Actor run finished';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a run of an Apify Actor finishes, or only when it ends a given way. Instant.';
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
