<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloListListsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_lists';
    }

    public function name(): string
    {
        return 'Apollo: List Lists';
    }

    public function description(): string
    {
        return 'Lists the lists of contacts and accounts in the workspace.';
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
        $lists = array_values(array_filter($this->apollo($run, $config, 'GET', '/labels'), 'is_array'));

        return ['lists' => $lists, 'count' => count($lists)];
    }
}
