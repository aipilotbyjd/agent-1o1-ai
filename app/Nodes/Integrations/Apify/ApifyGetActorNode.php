<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetActorNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_actor';
    }

    public function name(): string
    {
        return 'Apify: Get Actor';
    }

    public function description(): string
    {
        return 'Reads an Actor\'s details, versions and default run settings.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['actor' => $this->apify($run, $config, 'GET', '/actors/'.$this->actorId($config))];
    }
}
