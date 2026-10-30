<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PerplexitySearchNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_search';
    }

    public function name(): string
    {
        return 'Perplexity: Search';
    }

    public function description(): string
    {
        return 'Searches the web and returns ranked results with their excerpts, not a written answer.';
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
                'query' => Field::text('Search for', 'Several searches can be separated by new lines.'),
                'max_results' => Field::integer('Max results', null, 10, 1, 20),
                'search_type' => Field::advanced(Field::select('Kind of search', ['web', 'fast', 'people'], null, 'web')),
                'search_recency_filter' => Field::advanced(Field::select('From the last', ['hour', 'day', 'week', 'month', 'year'])),
                'search_domain_filter' => Field::advanced(Field::list('Sites', 'Only these sites, or put - before a site to leave it out.', 'example.com')),
                'search_language_filter' => Field::advanced(Field::list('Languages', 'Two-letter codes.', 'en')),
                'country' => Field::advanced(Field::text('Country', 'A two-letter code.', 'US')),
                'search_after_date' => Field::advanced(Field::text('Published after', 'As month/day/year.', '01/31/2026')),
                'search_before_date' => Field::advanced(Field::text('Published before', 'As month/day/year.', '12/31/2026')),
                'last_updated_after' => Field::advanced(Field::text('Updated after', 'As month/day/year.')),
                'last_updated_before' => Field::advanced(Field::text('Updated before', 'As month/day/year.')),
                'max_tokens' => Field::advanced(Field::integer('Max content in all (tokens)', null, null, 1)),
                'max_tokens_per_page' => Field::advanced(Field::integer('Max content per page (tokens)', null, null, 1)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $queries = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($config['query'] ?? ''))), fn (string $line): bool => $line !== ''));

        if ($queries === []) {
            throw new InvalidArgumentException('What to search for is required.');
        }

        $answer = $this->perplexity($run, $config, 'POST', '/search', $this->withoutEmpty([
            'query' => count($queries) === 1 ? $queries[0] : $queries,
            'max_results' => $this->intOf($config, 'max_results'),
            'search_type' => $this->textOf($config, 'search_type'),
            'search_recency_filter' => $this->textOf($config, 'search_recency_filter'),
            'search_domain_filter' => $this->stringList($config['search_domain_filter'] ?? []),
            'search_language_filter' => $this->stringList($config['search_language_filter'] ?? []),
            'country' => $this->textOf($config, 'country'),
            'search_after_date_filter' => $this->textOf($config, 'search_after_date'),
            'search_before_date_filter' => $this->textOf($config, 'search_before_date'),
            'last_updated_after_filter' => $this->textOf($config, 'last_updated_after'),
            'last_updated_before_filter' => $this->textOf($config, 'last_updated_before'),
            'max_tokens' => $this->intOf($config, 'max_tokens'),
            'max_tokens_per_page' => $this->intOf($config, 'max_tokens_per_page'),
        ]), timeout: 60);
        $results = array_values((array) ($answer['results'] ?? []));

        return ['results' => $results, 'count' => count($results), 'id' => $answer['id'] ?? null];
    }
}
