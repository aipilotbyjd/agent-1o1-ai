<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloGetCreditUsageNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_credit_usage';
    }

    public function name(): string
    {
        return 'Apollo: Get Credit Usage';
    }

    public function description(): string
    {
        return 'Reads how many credits the team has used in this cycle. Needs a master API key.';
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
        return $this->apollo($run, $config, 'POST', '/usage_stats/credit_usage_stats');
    }
}
