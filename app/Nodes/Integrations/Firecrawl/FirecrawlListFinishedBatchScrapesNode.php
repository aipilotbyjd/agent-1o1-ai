<?php

namespace App\Nodes\Integrations\Firecrawl;

class FirecrawlListFinishedBatchScrapesNode extends AbstractFirecrawlJobFeedNode
{
    public function type(): string
    {
        return 'firecrawl_list_finished_batch_scrapes';
    }

    public function name(): string
    {
        return 'Firecrawl: List Recent Batch Scrapes';
    }

    public function description(): string
    {
        return 'Lists the team\'s latest batch scrapes. As a trigger it starts the flow when one ends. Checked every few minutes.';
    }

    public function triggerName(): string
    {
        return 'Firecrawl: Batch scrape finished';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Firecrawl batch scrape ends. Read its pages with Get Batch Scrape Status. Checked every few minutes.';
    }

    protected function endpoint(): string
    {
        return 'batch_scrape';
    }

    protected function statusPath(string $id): string
    {
        return "/batch/scrape/{$id}";
    }
}
