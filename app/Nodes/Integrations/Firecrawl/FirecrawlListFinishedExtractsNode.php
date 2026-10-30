<?php

namespace App\Nodes\Integrations\Firecrawl;

class FirecrawlListFinishedExtractsNode extends AbstractFirecrawlJobFeedNode
{
    public function type(): string
    {
        return 'firecrawl_list_finished_extracts';
    }

    public function name(): string
    {
        return 'Firecrawl: List Recent Extracts';
    }

    public function description(): string
    {
        return 'Lists the team\'s latest extracts. As a trigger it starts the flow when one ends. Checked every few minutes.';
    }

    public function triggerName(): string
    {
        return 'Firecrawl: Extract finished';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Firecrawl extract ends. Read its data with Get Extract Status. Checked every few minutes.';
    }

    protected function endpoint(): string
    {
        return 'extract';
    }

    protected function statusPath(string $id): string
    {
        return "/extract/{$id}";
    }
}
