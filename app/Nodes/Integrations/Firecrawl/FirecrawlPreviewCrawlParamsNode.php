<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlPreviewCrawlParamsNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_preview_crawl_params';
    }

    public function name(): string
    {
        return 'Firecrawl: Preview Crawl Settings';
    }

    public function description(): string
    {
        return 'Shows the crawl settings Firecrawl would work out from a description in words, without starting anything.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url', 'prompt'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('Start at', null, 'https://example.com'),
                'prompt' => Field::textarea('Describe the crawl', null, 'Only the blog posts from this year'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/crawl/params-preview', ['url' => $this->webUrl($config), 'prompt' => $this->requiredString($config, 'prompt', 'The description')]);

        return ['settings' => $answer['data'] ?? []];
    }
}
