<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetCrawlErrorsNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_crawl_errors';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Crawl Errors';
    }

    public function description(): string
    {
        return 'Lists the pages a crawl could not read, and those robots.txt blocked.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['crawl_id'],
            'properties' => [
                ...$this->credentialFields(),
                'crawl_id' => Field::text('Crawl id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'GET', '/crawl/'.$this->pathId($config, 'crawl_id', 'The crawl id').'/errors');

        return ['errors' => $answer['errors'] ?? [], 'robots_blocked' => $answer['robotsBlocked'] ?? []];
    }
}
