<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyDeleteActorNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_delete_actor';
    }

    public function name(): string
    {
        return 'Apify: Delete Actor';
    }

    public function description(): string
    {
        return 'Deletes one of your Actors for good. A public Actor must be unpublished first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->apify($run, $config, 'DELETE', '/actors/'.$this->actorId($config));

        return ['deleted' => true];
    }
}
