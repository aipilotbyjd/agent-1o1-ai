<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateDealNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_deal';
    }

    public function name(): string
    {
        return 'Apollo: Update Deal';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on a deal. Needs a master API key.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['deal_id'],
            'properties' => [
                ...$this->credentialFields(),
                'deal_id' => Field::dynamic('Deal', 'apollo.deals'),
                ...$this->dealFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['deal' => $this->shapeDeal((array) ($this->apollo($run, $config, 'PATCH', '/opportunities/'.$this->recordId($config, 'deal_id', 'The deal'), [], $this->dealBody($config))['opportunity'] ?? []))];
    }
}
