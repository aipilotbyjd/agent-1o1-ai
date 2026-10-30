<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FirecrawlGetTokenUsageNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_token_usage';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Token Usage';
    }

    public function description(): string
    {
        return 'Reads the team\'s remaining extract tokens and billing period.';
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
        return ['usage' => $this->firecrawl($run, $config, 'GET', '/team/token-usage')['data'] ?? []];
    }
}
