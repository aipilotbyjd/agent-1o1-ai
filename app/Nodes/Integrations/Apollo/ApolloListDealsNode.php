<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloListDealsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_deals';
    }

    public function name(): string
    {
        return 'Apollo: List Deals';
    }

    public function description(): string
    {
        return 'Lists your team\'s deals. Needs a master API key.';
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
                'sort_by_field' => Field::advanced(Field::select('Sort by', ['amount' => 'Largest first', 'is_closed' => 'Closed first', 'is_won' => 'Won first'])),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'GET', '/opportunities/search', [...$this->only($config, ['sort_by_field']), ...$this->paging($config)]), 'opportunities', $this->shapeDeal(...));
    }
}
