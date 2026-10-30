<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetDatasetStatisticsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_dataset_statistics';
    }

    public function name(): string
    {
        return 'Apify: Get Dataset Statistics';
    }

    public function description(): string
    {
        return 'Reads statistics about a dataset\'s fields.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['dataset_id'],
            'properties' => [
                ...$this->credentialFields(),
                'dataset_id' => $this->datasetField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['statistics' => $this->apify($run, $config, 'GET', '/datasets/'.$this->pathId($config, 'dataset_id', 'The dataset').'/statistics')];
    }
}
