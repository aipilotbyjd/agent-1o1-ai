<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyRunActorAndGetResultsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_run_actor_and_get_results';
    }

    public function name(): string
    {
        return 'Apify: Run Actor And Get Results';
    }

    public function description(): string
    {
        return 'Runs an Actor, waits up to a minute, and returns its results. A longer run is handed back by id, with no results yet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['actor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'actor_id' => $this->actorField(),
                ...$this->runAndReadFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->runAndRead($run, $config, '/actors/'.$this->actorId($config).'/runs');
    }
}
