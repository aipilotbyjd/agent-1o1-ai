<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyWebSearchNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_web_search';
    }

    public function name(): string
    {
        return 'Apify: Web Search';
    }

    public function description(): string
    {
        return 'Searches the web and returns each result\'s page as markdown, through Apify\'s RAG Web Browser Actor. Costs what the Actor charges.';
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
                'query' => Field::textarea('Search for', 'A search, or one web address to read.'),
                'max_results' => Field::integer('Max results', null, 3, 1, 100),
                'output_formats' => Field::advanced(Field::list('Formats', 'markdown, text, html.', 'markdown')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $items = $this->apifyResponse($run, $config, 'POST', '/actors/apify~rag-web-browser/run-sync-get-dataset-items', $this->withoutEmpty([
            'query' => $this->requiredString($config, 'query', 'What to search for'),
            'maxResults' => $this->intOf($config, 'max_results'),
            'outputFormats' => $this->stringList($config['output_formats'] ?? []),
        ]), ['timeout' => self::MAX_WAIT_SECONDS], self::MAX_WAIT_SECONDS + 15)->json();

        return ['results' => array_values((array) $items), 'count' => count((array) $items)];
    }
}
