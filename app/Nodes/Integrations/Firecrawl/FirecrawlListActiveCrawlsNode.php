<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FirecrawlListActiveCrawlsNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_list_active_crawls';
    }

    public function name(): string
    {
        return 'Firecrawl: List Active Crawls';
    }

    public function description(): string
    {
        return 'Lists the team\'s crawls that are running now.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $crawls = array_values(array_filter((array) ($this->firecrawl($run, $config, 'GET', '/crawl/active')['crawls'] ?? []), 'is_array'));

        return ['crawls' => array_map(fn (array $crawl): array => ['id' => $crawl['id'] ?? null, 'url' => $crawl['url'] ?? null, 'options' => $crawl['options'] ?? null], $crawls), 'count' => count($crawls)];
    }
}
