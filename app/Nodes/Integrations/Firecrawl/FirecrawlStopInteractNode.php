<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlStopInteractNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_stop_interact';
    }

    public function name(): string
    {
        return 'Firecrawl: Stop Page Interaction';
    }

    public function description(): string
    {
        return 'Closes the browser session of a scraped page, to release its resources.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['scrape_id'],
            'properties' => [
                ...$this->credentialFields(),
                'scrape_id' => Field::text('Scrape id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->firecrawl($run, $config, 'DELETE', '/scrape/'.$this->pathId($config, 'scrape_id', 'The scrape id').'/interact');

        return ['stopped' => true];
    }
}
