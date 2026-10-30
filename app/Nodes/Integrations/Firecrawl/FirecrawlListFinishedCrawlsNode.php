<?php

namespace App\Nodes\Integrations\Firecrawl;

class FirecrawlListFinishedCrawlsNode extends AbstractFirecrawlJobFeedNode
{
    public function type(): string
    {
        return 'firecrawl_list_finished_crawls';
    }

    public function name(): string
    {
        return 'Firecrawl: List Recent Crawls';
    }

    public function description(): string
    {
        return 'Lists the team\'s latest crawls. As a trigger it starts the flow when a crawl ends. Checked every few minutes.';
    }

    public function triggerName(): string
    {
        return 'Firecrawl: Crawl finished';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Firecrawl crawl ends. Read its pages with Get Crawl Status. Checked every few minutes.';
    }

    protected function endpoint(): string
    {
        return 'crawl';
    }

    protected function statusPath(string $id): string
    {
        return "/crawl/{$id}";
    }
}
