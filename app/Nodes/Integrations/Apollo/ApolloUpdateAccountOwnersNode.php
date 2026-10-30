<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateAccountOwnersNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_account_owners';
    }

    public function name(): string
    {
        return 'Apollo: Update Account Owners';
    }

    public function description(): string
    {
        return 'Gives several accounts a new owner.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_ids', 'owner_id'],
            'properties' => [
                ...$this->credentialFields(),
                'account_ids' => Field::list('Account ids'),
                'owner_id' => Field::dynamic('Owner', 'apollo.users'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = ['account_ids' => $this->requiredList($config, 'account_ids', 'The account ids'), 'owner_id' => $this->requiredString($config, 'owner_id', 'The owner')];

        return $this->listResult($this->apollo($run, $config, 'POST', '/accounts/update_owners', $query), 'accounts', $this->shapeAccount(...));
    }
}
