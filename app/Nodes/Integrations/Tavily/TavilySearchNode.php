<?php

namespace App\Nodes\Integrations\Tavily;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TavilySearchNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_search';
    }

    public function name(): string
    {
        return 'Tavily: Search';
    }

    public function description(): string
    {
        return 'Searches the web for sources made for AI, with an optional written answer.';
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
                'topic' => Field::select('Topic', ['general', 'news', 'finance'], null, 'general'),
                'search_depth' => Field::select('Depth', ['basic', 'advanced', 'fast', 'ultra-fast'], 'Advanced costs 2 credits, the others 1.', 'basic'),
                'max_results' => Field::integer('Max results', null, 5, 0, 20),
                'include_answer' => Field::select('Written answer', ['none' => 'None', 'basic' => 'Short', 'advanced' => 'Detailed'], null, 'none'),
                'include_raw_content' => Field::advanced(Field::select('Page content', ['none' => 'None', 'markdown' => 'Markdown', 'text' => 'Text'], null, 'none')),
                'time_range' => Field::advanced(Field::select('From the last', ['day', 'week', 'month', 'year'])),
                'start_date' => Field::advanced(Field::text('From date', null, '2026-01-31')),
                'end_date' => Field::advanced(Field::text('To date', null, '2026-12-31')),
                'include_domains' => Field::advanced(Field::list('Only these sites')),
                'exclude_domains' => Field::advanced(Field::list('Not these sites')),
                'country' => Field::advanced(Field::text('Boost a country', 'In lower case, such as germany.')),
                'include_images' => Field::advanced(Field::boolean('Include images')),
                'include_image_descriptions' => Field::advanced(Field::boolean('Describe the images')),
                'include_favicon' => Field::advanced(Field::boolean('Include site icons')),
                'chunks_per_source' => Field::advanced(Field::integer('Passages per source', 'For the advanced depth.', null, 1, 3)),
                'auto_parameters' => Field::advanced(Field::boolean('Choose settings automatically')),
                'exact_match' => Field::advanced(Field::boolean('Only exact matches')),
                'safe_search' => Field::advanced(Field::boolean('Safe search')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->textOf($config, 'include_answer');
        $raw = $this->textOf($config, 'include_raw_content');
        $result = $this->tavily($run, $config, 'POST', '/search', $this->withoutEmpty([
            'query' => $this->requiredString($config, 'query', 'What to search for'),
            'topic' => $this->textOf($config, 'topic'),
            'search_depth' => $this->textOf($config, 'search_depth'),
            'max_results' => $this->intOf($config, 'max_results'),
            'include_answer' => in_array($answer, [null, 'none'], true) ? null : $answer,
            'include_raw_content' => in_array($raw, [null, 'none'], true) ? null : $raw,
            'time_range' => $this->textOf($config, 'time_range'),
            'start_date' => $this->textOf($config, 'start_date'),
            'end_date' => $this->textOf($config, 'end_date'),
            'include_domains' => $this->stringList($config['include_domains'] ?? []),
            'exclude_domains' => $this->stringList($config['exclude_domains'] ?? []),
            'country' => $this->textOf($config, 'country'),
            'include_images' => $this->boolOf($config, 'include_images'),
            'include_image_descriptions' => $this->boolOf($config, 'include_image_descriptions'),
            'include_favicon' => $this->boolOf($config, 'include_favicon'),
            'chunks_per_source' => $this->intOf($config, 'chunks_per_source'),
            'auto_parameters' => $this->boolOf($config, 'auto_parameters'),
            'exact_match' => $this->boolOf($config, 'exact_match'),
            'safe_search' => $this->boolOf($config, 'safe_search'),
            'include_usage' => true,
        ]), 60);

        return ['answer' => $result['answer'] ?? null, 'results' => array_values((array) ($result['results'] ?? [])), 'images' => array_values((array) ($result['images'] ?? [])), 'credits' => data_get($result, 'usage.credits'), 'request_id' => $result['request_id'] ?? null, 'response_time' => $result['response_time'] ?? null];
    }
}
