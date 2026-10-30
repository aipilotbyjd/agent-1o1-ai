<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FirecrawlGetCreditUsageNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_credit_usage';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Credit Usage';
    }

    public function description(): string
    {
        return 'Reads the team\'s remaining credits and billing period.';
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
        return ['usage' => $this->firecrawl($run, $config, 'GET', '/team/credit-usage')['data'] ?? []];
    }
}
