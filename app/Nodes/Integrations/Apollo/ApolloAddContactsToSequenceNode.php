<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloAddContactsToSequenceNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_add_contacts_to_sequence';
    }

    public function name(): string
    {
        return 'Apollo: Add Contacts to Sequence';
    }

    public function description(): string
    {
        return 'Adds contacts to a sequence, and an active sequence starts emailing them. Contacts Apollo skips are reported.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['sequence_id', 'contact_ids', 'send_email_from_email_account_id'],
            'properties' => [
                ...$this->credentialFields(),
                'sequence_id' => Field::dynamic('Sequence', 'apollo.sequences'),
                'contact_ids' => Field::list('Contact ids'),
                'send_email_from_email_account_id' => Field::dynamic('Send from', 'apollo.email_accounts'),
                'status' => Field::advanced(Field::select('Add them as', ['active' => 'Active', 'paused' => 'Paused'], null, 'active')),
                'sequence_no_email' => Field::advanced(Field::boolean('Add those without an email')),
                'sequence_unverified_email' => Field::advanced(Field::boolean('Add those with an unverified email')),
                'sequence_active_in_other_campaigns' => Field::advanced(Field::boolean('Add those active in other sequences')),
                'sequence_finished_in_other_campaigns' => Field::advanced(Field::boolean('Add those finished in other sequences')),
                'user_id' => Field::advanced(Field::dynamic('Added by', 'apollo.users')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sequence = $this->recordId($config, 'sequence_id', 'The sequence');
        $query = ['emailer_campaign_id' => $sequence, 'contact_ids' => $this->requiredList($config, 'contact_ids', 'The contact ids'), 'send_email_from_email_account_id' => $this->requiredString($config, 'send_email_from_email_account_id', 'The sending account'), ...$this->only($config, ['status', 'sequence_no_email', 'sequence_unverified_email', 'sequence_active_in_other_campaigns', 'sequence_finished_in_other_campaigns', 'user_id'])];
        $body = $this->apollo($run, $config, 'POST', "/emailer_campaigns/{$sequence}/add_contact_ids", $query);

        return ['contacts' => array_map($this->shapeContact(...), array_values(array_filter((array) ($body['contacts'] ?? []), 'is_array'))), 'skipped_contact_ids' => $body['skipped_contact_ids'] ?? []];
    }
}
