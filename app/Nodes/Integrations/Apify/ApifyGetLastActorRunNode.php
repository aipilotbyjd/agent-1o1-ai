<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetLastActorRunNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_last_actor_run';
    }

    public function name(): string
    {
        return 'Apify: Find Last Actor Run';
    }

    public function description(): string
    {
        return 'Finds an Actor\'s latest run, optionally with a given outcome.';
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
                'status' => Field::select('Outcome', ['SUCCEEDED', 'FAILED', 'ABORTED', 'TIMED-OUT', 'RUNNING'], 'Leave empty for the latest of any kind.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeRun($this->apify($run, $config, 'GET', '/actors/'.$this->actorId($config).'/runs/last', query: ['status' => $this->textOf($config, 'status')]));
    }
}
