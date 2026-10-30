<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FirecrawlListParseFormatsNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_list_parse_formats';
    }

    public function name(): string
    {
        return 'Firecrawl: List Parse Formats';
    }

    public function description(): string
    {
        return 'Lists the file types Parse File accepts.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['formats' => $this->firecrawl($run, $config, 'GET', '/parse/formats')['data'] ?? []];
    }
}
