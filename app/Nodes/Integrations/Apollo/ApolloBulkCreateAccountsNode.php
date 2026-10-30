<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloBulkCreateAccountsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_bulk_create_accounts';
    }

    public function name(): string
    {
        return 'Apollo: Bulk Create Accounts';
    }

    public function description(): string
    {
        return 'Creates up to 100 accounts in one call, and reports the ones that were already there.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['accounts'],
            'properties' => [
                ...$this->credentialFields(),
                'accounts' => Field::json('Accounts', 'A list of up to 100, each with name, domain, phone, raw_address and so on.', 'array'),
                'run_dedupe' => Field::boolean('Skip duplicates', 'Return an account that exists instead of making another.', true),
                'append_label_names' => Field::advanced(Field::list('Lists', 'The names of the lists to add them to.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'POST', '/accounts/bulk_create', [], ['accounts' => $this->records($config, 'accounts', 'The accounts'), 'run_dedupe' => (bool) ($config['run_dedupe'] ?? true), 'append_label_names' => $this->stringList($config, 'append_label_names') ?: null]);

        return ['created_accounts' => array_map($this->shapeAccount(...), array_values((array) ($body['created_accounts'] ?? []))), 'existing_accounts' => array_map($this->shapeAccount(...), array_values((array) ($body['existing_accounts'] ?? [])))];
    }
}
