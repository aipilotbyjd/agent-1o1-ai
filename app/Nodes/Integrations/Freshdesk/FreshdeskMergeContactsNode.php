<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskMergeContactsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_merge_contacts';
    }

    public function name(): string
    {
        return 'Freshdesk: Merge Contacts';
    }

    public function description(): string
    {
        return 'Merges contacts into a primary contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['primary_contact_id', 'secondary_contact_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'primary_contact_id' => Field::text('Primary contact id', 'The contact that stays.'),
                'secondary_contact_ids' => Field::text('Contacts to merge', 'Separated by commas.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $primary = (int) $this->numericId($config, 'primary_contact_id', 'The primary contact');
        $this->freshdesk($run, $config, 'POST', '/contacts/merge', [], ['primary_contact_id' => $primary, 'secondary_contact_ids' => $this->idList($config, 'secondary_contact_ids', 'The contacts to merge')]);

        return ['merged' => true, 'primary_contact_id' => $primary];
    }
}
