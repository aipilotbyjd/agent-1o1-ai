<?php

namespace App\Nodes\Integrations\Apify;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ApifyListActorBuildsNode extends AbstractApifyNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'apify_list_actor_builds';
    }

    public function name(): string
    {
        return 'Apify: List Actor Builds';
    }

    public function description(): string
    {
        return 'Lists an Actor\'s latest builds, newest first. As a trigger it starts the flow when a build finishes.';
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
        $wanted = $this->wantedStatuses($config);
        $builds = array_values(array_filter($this->listItems($run, $config, '/actors/'.$this->actorId($config).'/builds'), fn (array $build): bool => $wanted === [] || in_array($build['status'] ?? null, $wanted, true)));

        return ['builds' => array_map($this->shapeBuild(...), $builds), 'count' => count($builds)];
    }

    public function triggerName(): string
    {
        return 'Apify: Actor build finished';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a build of an Apify Actor finishes, or only when it ends a given way. Instant.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'builds';
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
