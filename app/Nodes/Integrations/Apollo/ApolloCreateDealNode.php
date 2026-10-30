<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloCreateDealNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_create_deal';
    }

    public function name(): string
    {
        return 'Apollo: Create Deal';
    }

    public function description(): string
    {
        return 'Creates a deal to track a possible sale. Needs a master API key.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->dealFields(),
                'account_id' => Field::advanced(Field::dynamic('Account', 'apollo.accounts')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->requiredString($config, 'name', 'The name');

        return ['deal' => $this->shapeDeal((array) ($this->apollo($run, $config, 'POST', '/opportunities', [], [...$this->dealBody($config), ...$this->only($config, ['account_id'])])['opportunity'] ?? []))];
    }
}
