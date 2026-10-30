<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApifyDeleteDatasetNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_delete_dataset';
    }

    public function name(): string
    {
        return 'Apify: Delete Dataset';
    }

    public function description(): string
    {
        return 'Deletes a dataset and its items for good.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->apify($run, $config, 'DELETE', '/datasets/'.$this->pathId($config, 'dataset_id', 'The dataset'));

        return ['deleted' => true];
    }
}
