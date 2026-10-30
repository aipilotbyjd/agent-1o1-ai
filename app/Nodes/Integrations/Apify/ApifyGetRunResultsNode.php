<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyGetRunResultsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_run_results';
    }

    public function name(): string
    {
        return 'Apify: Get Run Results';
    }

    public function description(): string
    {
        return 'Reads the results of a run from its default dataset.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['run_id'],
            'properties' => [
                ...$this->credentialFields(),
                'run_id' => Field::text('Run id'),
                ...$this->datasetReadFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $items = array_values((array) $this->apifyResponse($run, $config, 'GET', '/actor-runs/'.$this->pathId($config, 'run_id', 'The run id').'/dataset/items', query: $this->datasetQuery($config))->json());

        return ['items' => $items, 'count' => count($items)];
    }
}
