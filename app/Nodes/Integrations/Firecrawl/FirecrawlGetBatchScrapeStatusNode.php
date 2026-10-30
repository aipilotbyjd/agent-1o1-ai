<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetBatchScrapeStatusNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_batch_scrape_status';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Batch Scrape Status';
    }

    public function description(): string
    {
        return 'Reads how far a batch scrape is, and the pages read so far.';
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
                'batch_id' => Field::text('Batch id', 'The id Start Batch Scrape returned.'),
                'max_pages' => Field::integer('Max pages to return', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'batch_id', 'The batch id');

        return $this->shapeJob($id, $this->firecrawl($run, $config, 'GET', "/batch/scrape/{$id}", timeout: 60), $this->limit($config, 100, 1000, 'max_pages'));
    }
}
