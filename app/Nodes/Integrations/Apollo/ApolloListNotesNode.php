<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloListNotesNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_notes';
    }

    public function name(): string
    {
        return 'Apollo: List Notes';
    }

    public function description(): string
    {
        return 'Lists the notes on a contact, account or deal.';
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
                'contact_id' => Field::dynamic('Contact', 'apollo.contacts'),
                'account_id' => Field::dynamic('Account', 'apollo.accounts'),
                'opportunity_id' => Field::dynamic('Deal', 'apollo.deals'),
                'start_date' => Field::advanced(Field::text('Since', 'YYYY-MM-DD.')),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'GET', '/notes', [...$this->only($config, ['contact_id', 'account_id', 'opportunity_id', 'start_date', 'limit']), 'sort_by_field' => 'created_at', 'sort_direction' => 'desc']), 'notes');
    }
}
