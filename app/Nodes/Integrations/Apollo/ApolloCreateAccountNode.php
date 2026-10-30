<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloCreateAccountNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_create_account';
    }

    public function name(): string
    {
        return 'Apollo: Create Account';
    }

    public function description(): string
    {
        return 'Saves a company as an account. Apollo does not check for duplicates, so search first if one may exist.';
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
                ...$this->accountFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->requiredString($config, 'name', 'The name');

        return ['account' => $this->shapeAccount((array) ($this->apollo($run, $config, 'POST', '/accounts', [], $this->accountBody($config))['account'] ?? []))];
    }
}
