<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetScrapeNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_scrape';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Scrape';
    }

    public function description(): string
    {
        return 'Reads back an earlier scrape by its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['scrape_id'],
            'properties' => [
                ...$this->credentialFields(),
                'scrape_id' => Field::text('Scrape id', 'The scrape_id of an earlier scrape.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'GET', '/scrape/'.$this->pathId($config, 'scrape_id', 'The scrape id'));

        return ['status' => $answer['status'] ?? null, ...$this->shapePage((array) ($answer['data'] ?? []))];
    }
}
