<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlStartCrawlNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_start_crawl';
    }

    public function name(): string
    {
        return 'Firecrawl: Start Crawl';
    }

    public function description(): string
    {
        return 'Starts reading a whole site and returns the job id at once. Read the pages with Get Crawl Status, or start a flow with the Crawl Finished trigger.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('Start at', null, 'https://example.com'),
                'limit' => Field::integer('Max pages', 'Each page costs credits.', 100, 1),
                'prompt' => Field::textarea('Or describe the crawl', 'In words: Firecrawl works out the settings.'),
                'include_paths' => Field::advanced(Field::list('Only paths like', 'Patterns.', 'blog/.*')),
                'exclude_paths' => Field::advanced(Field::list('Not paths like')),
                'max_depth' => Field::advanced(Field::integer('How deep', 'Links to follow from the start.', null, 1)),
                'crawl_entire_domain' => Field::advanced(Field::boolean('Crawl the entire domain', 'Also follows links to sibling and parent pages.')),
                'allow_subdomains' => Field::advanced(Field::boolean('Allow subdomains')),
                'allow_external_links' => Field::advanced(Field::boolean('Allow links to other sites')),
                'ignore_query_parameters' => Field::advanced(Field::boolean('Ignore query parameters')),
                'sitemap' => Field::advanced(Field::select('Sitemap', ['include', 'skip', 'only'], null, 'include')),
                'delay' => Field::advanced(Field::integer('Seconds between pages', null, null, 0)),
                'max_concurrency' => Field::advanced(Field::integer('Pages at once', null, null, 1)),
                ...$this->scrapeFields(),
                ...$this->webhookFields('started, page, completed, failed'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/crawl', $this->withoutEmpty([
            'url' => $this->webUrl($config),
            'limit' => $this->intOf($config, 'limit'),
            'prompt' => $this->textOf($config, 'prompt'),
            'includePaths' => $this->stringList($config['include_paths'] ?? []),
            'excludePaths' => $this->stringList($config['exclude_paths'] ?? []),
            'maxDiscoveryDepth' => $this->intOf($config, 'max_depth'),
            'crawlEntireDomain' => $this->boolOf($config, 'crawl_entire_domain'),
            'allowSubdomains' => $this->boolOf($config, 'allow_subdomains'),
            'allowExternalLinks' => $this->boolOf($config, 'allow_external_links'),
            'ignoreQueryParameters' => $this->boolOf($config, 'ignore_query_parameters'),
            'sitemap' => $this->textOf($config, 'sitemap'),
            'delay' => $this->intOf($config, 'delay'),
            'maxConcurrency' => $this->intOf($config, 'max_concurrency'),
            'scrapeOptions' => $this->scrapeOptions($config),
            'webhook' => $this->webhook($config),
        ]));

        return ['id' => $answer['id'] ?? null, 'status_url' => $answer['url'] ?? null];
    }
}
