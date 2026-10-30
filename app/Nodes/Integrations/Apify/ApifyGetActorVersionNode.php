<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetActorVersionNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_actor_version';
    }

    public function name(): string
    {
        return 'Apify: Get Actor Version';
    }

    public function description(): string
    {
        return 'Reads a version of an Actor, with its source files.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['actor_id', 'version'],
            'properties' => [
                ...$this->credentialFields(),
                'actor_id' => $this->actorField(),
                'version' => Field::text('Version', null, '0.1'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['version' => $this->apify($run, $config, 'GET', '/actors/'.$this->actorId($config).'/versions/'.$this->pathId($config, 'version', 'The version'))];
    }
}
