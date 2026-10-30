<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyRunActorNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_run_actor';
    }

    public function name(): string
    {
        return 'Apify: Run Actor';
    }

    public function description(): string
    {
        return 'Starts an Actor and returns the run at once, or after a short wait. Follow it with Get Run, or the Actor Run Finished trigger.';
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
                ...$this->runFields(),
                'wait_seconds' => Field::advanced(Field::integer('Wait up to (seconds)', 'Leave empty not to wait.', null, 0, self::MAX_WAIT_SECONDS)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeRun($this->startRun($run, $config, '/actors/'.$this->actorId($config).'/runs'));
    }
}
