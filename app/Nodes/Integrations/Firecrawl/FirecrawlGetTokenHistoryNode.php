<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetTokenHistoryNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_token_history';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Token Usage History';
    }

    public function description(): string
    {
        return 'Reads the team\'s extract token use month by month, optionally per API key.';
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
                'by_api_key' => Field::boolean('Per API key'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['periods' => $this->firecrawl($run, $config, 'GET', '/team/token-usage/historical', query: ['byApiKey' => $this->boolOf($config, 'by_api_key') ? 'true' : null])['periods'] ?? []];
    }
}
