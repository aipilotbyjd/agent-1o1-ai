<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlScrapeNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_scrape';
    }

    public function name(): string
    {
        return 'Firecrawl: Scrape Page';
    }

    public function description(): string
    {
        return 'Reads one page as clean markdown, HTML, links, a summary or structured JSON. Works with PDFs and pages that need JavaScript.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('URL', null, 'https://example.com'),
                ...$this->scrapeFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/scrape', ['url' => $this->webUrl($config), ...$this->scrapeOptions($config)], timeout: 75);

        return $this->shapePage((array) ($answer['data'] ?? []));
    }
}
