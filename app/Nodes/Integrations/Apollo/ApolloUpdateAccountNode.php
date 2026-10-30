<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateAccountNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_account';
    }

    public function name(): string
    {
        return 'Apollo: Update Account';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on an account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id'],
            'properties' => [
                ...$this->credentialFields(),
                'account_id' => Field::dynamic('Account', 'apollo.accounts'),
                ...$this->accountFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['account' => $this->shapeAccount((array) ($this->apollo($run, $config, 'PATCH', '/accounts/'.$this->recordId($config, 'account_id', 'The account'), [], $this->accountBody($config))['account'] ?? []))];
    }
}
