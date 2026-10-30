<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetAccountNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_account';
    }

    public function name(): string
    {
        return 'Apollo: Get Account';
    }

    public function description(): string
    {
        return 'Reads one account by its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id'],
            'properties' => [
                ...$this->credentialFields(),
                'account_id' => Field::dynamic('Account', 'apollo.accounts'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'GET', '/accounts/'.$this->recordId($config, 'account_id', 'The account'));

        return ['account' => $this->shapeAccount((array) ($body['account'] ?? $body))];
    }
}
