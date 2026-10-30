<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlStartBatchScrapeNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_start_batch_scrape';
    }

    public function name(): string
    {
        return 'Firecrawl: Start Batch Scrape';
    }

    public function description(): string
    {
        return 'Starts reading a list of pages and returns the job id at once. Read them with Get Batch Scrape Status, or start a flow with the Batch Scrape Finished trigger.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['urls'],
            'properties' => [
                ...$this->credentialFields(),
                'urls' => Field::list('Pages', null, 'https://example.com/a'),
                'max_concurrency' => Field::advanced(Field::integer('Pages at once', null, null, 1)),
                ...$this->scrapeFields(),
                ...$this->webhookFields('started, page, completed'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/batch/scrape', $this->withoutEmpty([
            'urls' => $this->webUrls($config),
            'maxConcurrency' => $this->intOf($config, 'max_concurrency'),
            'webhook' => $this->webhook($config),
            ...$this->scrapeOptions($config),
        ]));

        return ['id' => $answer['id'] ?? null, 'status_url' => $answer['url'] ?? null, 'invalid_urls' => $answer['invalidURLs'] ?? []];
    }
}
