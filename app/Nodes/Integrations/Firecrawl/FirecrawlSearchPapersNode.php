<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlSearchPapersNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_search_papers';
    }

    public function name(): string
    {
        return 'Firecrawl: Search Research Papers';
    }

    public function description(): string
    {
        return 'Searches abstracts from PubMed, bioRxiv, medRxiv and arXiv.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::textarea('Search for'),
                'k' => Field::integer('Max results', null, 10, 1, 100),
                'authors' => Field::advanced(Field::text('Authors')),
                'categories' => Field::advanced(Field::text('Categories')),
                'from' => Field::advanced(Field::text('From date', null, '2024-01-01')),
                'to' => Field::advanced(Field::text('To date', null, '2026-01-01')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $results = array_values((array) ($this->firecrawl($run, $config, 'GET', '/search/research/papers', query: ['query' => $this->requiredString($config, 'query', 'What to search for'), 'k' => $this->intOf($config, 'k'), 'authors' => $this->textOf($config, 'authors'), 'categories' => $this->textOf($config, 'categories'), 'from' => $this->textOf($config, 'from'), 'to' => $this->textOf($config, 'to')], timeout: 60)['results'] ?? []));

        return ['results' => $results, 'count' => count($results)];
    }
}
