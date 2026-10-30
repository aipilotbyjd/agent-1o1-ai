<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyBuildActorNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_build_actor';
    }

    public function name(): string
    {
        return 'Apify: Build Actor';
    }

    public function description(): string
    {
        return 'Starts building a version of an Actor and returns the build at once. Follow it with Get Build, or the Actor Build Finished trigger.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'tag' => Field::text('Tag', null, 'latest'),
                'use_cache' => Field::advanced(Field::boolean('Use the build cache')),
                'wait_seconds' => Field::advanced(Field::integer('Wait up to (seconds)', 'Leave empty not to wait.', null, 0, self::MAX_WAIT_SECONDS)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $wait = min(self::MAX_WAIT_SECONDS, $this->intOf($config, 'wait_seconds') ?? 0);

        return $this->shapeBuild($this->apify($run, $config, 'POST', '/actors/'.$this->actorId($config).'/builds', [], ['version' => $this->requiredString($config, 'version', 'The version'), 'tag' => $this->textOf($config, 'tag'), 'useCache' => $this->boolOf($config, 'use_cache'), 'waitForFinish' => $wait > 0 ? $wait : null], self::MAX_WAIT_SECONDS + 15));
    }
}
