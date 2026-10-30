<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyGetDatasetItemsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_get_dataset_items';
    }

    public function name(): string
    {
        return 'Apify: Get Dataset Items';
    }

    public function description(): string
    {
        return 'Reads the items of a dataset, such as a run\'s results.';
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
                ...$this->datasetReadFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $items = $this->datasetItems($run, $config, $this->pathId($config, 'dataset_id', 'The dataset'));

        return ['items' => $items, 'count' => count($items)];
    }
}
