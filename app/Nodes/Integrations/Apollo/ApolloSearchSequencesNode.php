<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloSearchSequencesNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_search_sequences';
    }

    public function name(): string
    {
        return 'Apollo: Search Sequences';
    }

    public function description(): string
    {
        return 'Finds sequences by name. Needs a master API key.';
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
                'q_name' => Field::text('Name'),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'POST', '/emailer_campaigns/search', [...$this->only($config, ['q_name']), ...$this->paging($config)]), 'emailer_campaigns');
    }
}
