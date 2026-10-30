<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetBatchScrapeErrorsNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_batch_scrape_errors';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Batch Scrape Errors';
    }

    public function description(): string
    {
        return 'Lists the pages a batch scrape could not read, and those robots.txt blocked.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['batch_id'],
            'properties' => [
                ...$this->credentialFields(),
                'batch_id' => Field::text('Batch id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'GET', '/batch/scrape/'.$this->pathId($config, 'batch_id', 'The batch id').'/errors');

        return ['errors' => $answer['errors'] ?? [], 'robots_blocked' => $answer['robotsBlocked'] ?? []];
    }
}
