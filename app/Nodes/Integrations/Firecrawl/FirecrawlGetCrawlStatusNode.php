<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetCrawlStatusNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_crawl_status';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Crawl Status';
    }

    public function description(): string
    {
        return 'Reads how far a crawl is, and the pages read so far.';
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
                'crawl_id' => Field::text('Crawl id', 'The id Start Crawl returned.'),
                'max_pages' => Field::integer('Max pages to return', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'crawl_id', 'The crawl id');

        return $this->shapeJob($id, $this->firecrawl($run, $config, 'GET', "/crawl/{$id}", timeout: 60), $this->limit($config, 100, 1000, 'max_pages'));
    }
}
