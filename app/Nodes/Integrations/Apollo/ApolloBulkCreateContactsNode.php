<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloBulkCreateContactsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_bulk_create_contacts';
    }

    public function name(): string
    {
        return 'Apollo: Bulk Create Contacts';
    }

    public function description(): string
    {
        return 'Creates up to 100 contacts in one call, and reports the ones that were already there without changing them.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contacts'],
            'properties' => [
                ...$this->credentialFields(),
                'contacts' => Field::json('Contacts', 'A list of up to 100, each with first_name, last_name, email, title, organization_name and so on.', 'array'),
                'run_dedupe' => Field::boolean('Skip duplicates', 'Return a contact that exists instead of making another.', true),
                'owner_id' => Field::advanced(Field::dynamic('Owner', 'apollo.users')),
                'append_label_names' => Field::advanced(Field::list('Lists', 'The names of the lists to add them to.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'POST', '/contacts/bulk_create', [], ['contacts' => $this->records($config, 'contacts', 'The contacts'), 'run_dedupe' => (bool) ($config['run_dedupe'] ?? true), 'owner_id' => $config['owner_id'] ?? null, 'append_label_names' => $this->stringList($config, 'append_label_names') ?: null]);

        return ['created_contacts' => array_map($this->shapeContact(...), array_values((array) ($body['created_contacts'] ?? []))), 'existing_contacts' => array_map($this->shapeContact(...), array_values((array) ($body['existing_contacts'] ?? [])))];
    }
}
