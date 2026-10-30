<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * The team's monitors. Firecrawl takes the key as a bearer token and can't search
 * them, so a search filters the list.
 */
class FirecrawlOptions extends HttpOptionsSource
{
    public function sources(): array
    {
        return ['firecrawl.monitors'];
    }

    protected function appName(): string
    {
        return 'Firecrawl';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        $monitors = collect($this->getJson($query, 'https://api.firecrawl.dev/v2/monitor', ['limit' => 100])['data'] ?? [])
            ->filter(fn (mixed $monitor): bool => is_array($monitor) && isset($monitor['id']) && $query->matches((string) ($monitor['name'] ?? '')));

        return new NodeOptionsPage($monitors->map(fn (array $monitor): NodeOption => new NodeOption((string) $monitor['id'], $monitor['name'] ?? null, $monitor['status'] ?? null)));
    }
}
