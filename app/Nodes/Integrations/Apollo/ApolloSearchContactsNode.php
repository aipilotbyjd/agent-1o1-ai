<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloSearchContactsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_search_contacts';
    }

    public function name(): string
    {
        return 'Apollo: Search Contacts';
    }

    public function description(): string
    {
        return 'Searches the people your team saved as contacts.';
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
                'q_keywords' => Field::text('Keywords', 'Name, title, company or email.'),
                'contact_stage_ids' => Field::advanced(Field::list('Stage ids')),
                'contact_label_ids' => Field::advanced(Field::list('List ids')),
                'sort_by_field' => Field::advanced(Field::select('Sort by', ['contact_last_activity_date' => 'Last activity', 'contact_email_last_opened_at' => 'Email last opened', 'contact_email_last_clicked_at' => 'Email last clicked', 'contact_created_at' => 'Created', 'contact_updated_at' => 'Updated'])),
                'sort_ascending' => Field::advanced(Field::boolean('Oldest first')),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [...$this->only($config, ['q_keywords', 'sort_by_field']), 'contact_stage_ids' => $this->stringList($config, 'contact_stage_ids') ?: null, 'contact_label_ids' => $this->stringList($config, 'contact_label_ids') ?: null, 'sort_ascending' => (bool) ($config['sort_ascending'] ?? false), ...$this->paging($config)];

        return $this->listResult($this->apollo($run, $config, 'POST', '/contacts/search', [], $body), 'contacts', $this->shapeContact(...));
    }
}
