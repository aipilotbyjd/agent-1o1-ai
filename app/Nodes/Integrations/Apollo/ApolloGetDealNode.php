<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetDealNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_deal';
    }

    public function name(): string
    {
        return 'Apollo: Get Deal';
    }

    public function description(): string
    {
        return 'Reads one deal by its id. Needs a master API key.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['deal_id'],
            'properties' => [
                ...$this->credentialFields(),
                'deal_id' => Field::dynamic('Deal', 'apollo.deals'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['deal' => $this->shapeDeal((array) ($this->apollo($run, $config, 'GET', '/opportunities/'.$this->recordId($config, 'deal_id', 'The deal'))['opportunity'] ?? []))];
    }
}
