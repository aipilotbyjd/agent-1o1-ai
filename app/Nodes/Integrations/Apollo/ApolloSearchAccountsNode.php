<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloSearchAccountsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_search_accounts';
    }

    public function name(): string
    {
        return 'Apollo: Search Accounts';
    }

    public function description(): string
    {
        return 'Searches the companies your team saved as accounts.';
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
                'q_organization_name' => Field::text('Name'),
                'account_stage_ids' => Field::advanced(Field::list('Stage ids')),
                'account_label_ids' => Field::advanced(Field::list('List ids')),
                'sort_by_field' => Field::advanced(Field::select('Sort by', ['account_last_activity_date' => 'Last activity', 'account_created_at' => 'Created', 'account_updated_at' => 'Updated'])),
                'sort_ascending' => Field::advanced(Field::boolean('Oldest first')),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [...$this->only($config, ['q_organization_name', 'sort_by_field']), 'account_stage_ids' => $this->stringList($config, 'account_stage_ids') ?: null, 'account_label_ids' => $this->stringList($config, 'account_label_ids') ?: null, 'sort_ascending' => (bool) ($config['sort_ascending'] ?? false), ...$this->paging($config)];

        return $this->listResult($this->apollo($run, $config, 'POST', '/accounts/search', [], $body), 'accounts', $this->shapeAccount(...));
    }
}
