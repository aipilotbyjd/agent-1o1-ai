<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlCancelBatchScrapeNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_cancel_batch_scrape';
    }

    public function name(): string
    {
        return 'Firecrawl: Cancel Batch Scrape';
    }

    public function description(): string
    {
        return 'Stops a running batch scrape.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $id = $this->pathId($config, 'batch_id', 'The batch id');
        $answer = $this->firecrawl($run, $config, 'DELETE', "/batch/scrape/{$id}");

        return ['id' => $id, 'status' => $answer['status'] ?? null];
    }
}
