<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyListStoresNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_list_stores';
    }

    public function name(): string
    {
        return 'Apify: List Key-Value Stores';
    }

    public function description(): string
    {
        return 'Lists the key-value stores in your account, newest first.';
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
                'include_unnamed' => Field::boolean('Include unnamed ones', 'A run\'s default store has no name.'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $stores = $this->listItems($run, $config, '/key-value-stores', ['unnamed' => $this->boolOf($config, 'include_unnamed')]);

        return ['stores' => $stores, 'count' => count($stores)];
    }
}
