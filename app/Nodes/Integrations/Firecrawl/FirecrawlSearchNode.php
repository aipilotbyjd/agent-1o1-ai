<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlSearchNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_search';
    }

    public function name(): string
    {
        return 'Firecrawl: Search';
    }

    public function description(): string
    {
        return 'Searches the web, news and images, and can read each result\'s page as well.';
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
                'limit' => Field::integer('Max results', null, 10, 1, 100),
                'sources' => Field::list('Sources', 'web, news, images, or alexandria to find data tools. Leave empty for the web.', 'web'),
                'scrape_results' => Field::boolean('Read each result\'s page', 'Costs more credits.'),
                'scrape_formats' => Field::advanced(Field::list('Page formats', 'markdown, html, links, summary...', 'markdown')),
                'categories' => Field::advanced(Field::list('Categories', 'github, research, pdf...')),
                'include_domains' => Field::advanced(Field::list('Only these sites')),
                'exclude_domains' => Field::advanced(Field::list('Not these sites')),
                'tbs' => Field::advanced(Field::text('Time range', 'qdr:h, qdr:d, qdr:w, qdr:m, qdr:y, or a custom cdr: range.', 'qdr:w')),
                'location' => Field::advanced(Field::text('Location', null, 'Germany')),
                'country' => Field::advanced(Field::text('Country', 'A two-letter code.', 'US')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $formats = $this->stringList($config['scrape_formats'] ?? []);
        $answer = $this->firecrawl($run, $config, 'POST', '/search', $this->withoutEmpty([
            'query' => $this->requiredString($config, 'query', 'What to search for'),
            'limit' => $this->limit($config, 10, 100),
            'sources' => $this->stringList($config['sources'] ?? []),
            'categories' => $this->stringList($config['categories'] ?? []),
            'includeDomains' => $this->stringList($config['include_domains'] ?? []),
            'excludeDomains' => $this->stringList($config['exclude_domains'] ?? []),
            'tbs' => $this->textOf($config, 'tbs'),
            'location' => $this->textOf($config, 'location'),
            'country' => $this->textOf($config, 'country'),
            'scrapeOptions' => $this->boolOf($config, 'scrape_results') ? ['formats' => $formats === [] ? ['markdown'] : $formats] : null,
        ]), timeout: 75);

        return [
            'web' => data_get($answer, 'data.web', []),
            'news' => data_get($answer, 'data.news', []),
            'images' => data_get($answer, 'data.images', []),
            'tools' => data_get($answer, 'data.tools', []),
            'id' => $answer['id'] ?? null,
            'credits_used' => $answer['creditsUsed'] ?? null,
        ];
    }
}
