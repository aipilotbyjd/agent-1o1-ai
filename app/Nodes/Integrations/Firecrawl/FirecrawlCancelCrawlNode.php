<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlCancelCrawlNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_cancel_crawl';
    }

    public function name(): string
    {
        return 'Firecrawl: Cancel Crawl';
    }

    public function description(): string
    {
        return 'Stops a running crawl.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->pathId($config, 'crawl_id', 'The crawl id');
        $answer = $this->firecrawl($run, $config, 'DELETE', "/crawl/{$id}");

        return ['id' => $id, 'status' => $answer['status'] ?? null];
    }
}
