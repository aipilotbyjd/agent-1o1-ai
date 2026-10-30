<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyUpdateActorVersionNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_update_actor_version';
    }

    public function name(): string
    {
        return 'Apify: Update Actor Version';
    }

    public function description(): string
    {
        return 'Changes a version of an Actor: its source files, build tag or environment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['actor_id', 'version', 'changes'],
            'properties' => [
                ...$this->credentialFields(),
                'actor_id' => $this->actorField(),
                'version' => Field::text('Version', null, '0.1'),
                'changes' => Field::json('Changes', 'The version fields to change, such as sourceFiles, as in Apify\'s docs.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['version' => $this->apify($run, $config, 'PUT', '/actors/'.$this->actorId($config).'/versions/'.$this->pathId($config, 'version', 'The version'), $this->decoded($config['changes'] ?? null, 'The changes') ?? [])];
    }
}
