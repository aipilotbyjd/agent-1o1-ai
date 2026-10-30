<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyListDatasetsNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_list_datasets';
    }

    public function name(): string
    {
        return 'Apify: List Datasets';
    }

    public function description(): string
    {
        return 'Lists the datasets in your account, newest first.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'include_unnamed' => Field::boolean('Include unnamed ones', 'A run\'s default dataset has no name.'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $datasets = $this->listItems($run, $config, '/datasets', ['unnamed' => $this->boolOf($config, 'include_unnamed')]);

        return ['datasets' => $datasets, 'count' => count($datasets)];
    }
}
