<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifySearchStoreNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_search_store';
    }

    public function name(): string
    {
        return 'Apify: Search Store';
    }

    public function description(): string
    {
        return 'Finds ready-made Actors in Apify Store.';
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
                'search' => Field::text('Search for', null, 'google maps'),
                'limit' => Field::integer('Max results', null, 25, 1, 1000),
                'offset' => Field::advanced(Field::integer('Skip', null, null, 0)),
                'sort_by' => Field::advanced(Field::select('Sort by', ['relevance', 'popularity', 'newest', 'lastUpdate'], null, 'relevance')),
                'category' => Field::advanced(Field::text('Category', null, 'AI')),
                'username' => Field::advanced(Field::text('By user')),
                'pricing_model' => Field::advanced(Field::select('Pricing', ['FREE', 'PRICE_PER_DATASET_ITEM', 'PAY_PER_EVENT'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->apify($run, $config, 'GET', '/store', query: ['search' => $this->textOf($config, 'search'), 'limit' => $this->limit($config, 25, 1000), 'offset' => $this->intOf($config, 'offset'), 'sortBy' => $this->textOf($config, 'sort_by'), 'category' => $this->textOf($config, 'category'), 'username' => $this->textOf($config, 'username'), 'pricingModel' => $this->textOf($config, 'pricing_model')]);
        $actors = array_values(array_filter((array) ($page['items'] ?? []), 'is_array'));

        return ['actors' => array_map(fn (array $actor): array => ['id' => $actor['id'] ?? null, 'name' => isset($actor['username'], $actor['name']) ? "{$actor['username']}/{$actor['name']}" : ($actor['name'] ?? null), 'title' => $actor['title'] ?? null, 'description' => $actor['description'] ?? null, 'url' => $actor['url'] ?? null, 'stats' => $actor['stats'] ?? null, 'pricing' => $actor['currentPricingInfo'] ?? null], $actors), 'count' => count($actors), 'total' => $page['total'] ?? null];
    }
}
