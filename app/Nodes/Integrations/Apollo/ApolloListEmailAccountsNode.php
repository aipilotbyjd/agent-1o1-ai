<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloListEmailAccountsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_email_accounts';
    }

    public function name(): string
    {
        return 'Apollo: List Email Accounts';
    }

    public function description(): string
    {
        return 'Lists the mailboxes linked to Apollo, which sequences send from.';
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
        return $this->listResult($this->apollo($run, $config, 'GET', '/email_accounts'), 'email_accounts');
    }
}
