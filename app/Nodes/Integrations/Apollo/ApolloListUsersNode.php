<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloListUsersNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_users';
    }

    public function name(): string
    {
        return 'Apollo: List Users';
    }

    public function description(): string
    {
        return 'Lists the people on your Apollo team. Needs a master API key.';
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
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'GET', '/users/search', $this->paging($config)), 'users');
    }
}
