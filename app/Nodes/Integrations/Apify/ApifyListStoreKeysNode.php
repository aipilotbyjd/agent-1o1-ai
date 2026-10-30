<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyListStoreKeysNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_list_store_keys';
    }

    public function name(): string
    {
        return 'Apify: List Key-Value Store Keys';
    }

    public function description(): string
    {
        return 'Lists the keys in a key-value store.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['store_id'],
            'properties' => [
                ...$this->credentialFields(),
                'store_id' => $this->storeField(),
                'prefix' => Field::text('Starting with'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
                'start_after' => Field::advanced(Field::text('Start after key')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->apify($run, $config, 'GET', '/key-value-stores/'.$this->pathId($config, 'store_id', 'The store').'/keys', query: ['prefix' => $this->textOf($config, 'prefix'), 'limit' => $this->limit($config, 100, 1000), 'exclusiveStartKey' => $this->textOf($config, 'start_after')]);

        return ['keys' => $page['items'] ?? [], 'count' => $page['count'] ?? null, 'has_more' => $page['isTruncated'] ?? false, 'next_key' => $page['nextExclusiveStartKey'] ?? null];
    }
}
