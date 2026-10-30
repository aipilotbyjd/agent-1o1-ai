<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloListAccountStagesNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_account_stages';
    }

    public function name(): string
    {
        return 'Apollo: List Account Stages';
    }

    public function description(): string
    {
        return 'Lists the stages an account can be in.';
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
        return $this->listResult($this->apollo($run, $config, 'GET', '/account_stages'), 'account_stages');
    }
}
