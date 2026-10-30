<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ApolloBulkUpdateContactsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_bulk_update_contacts';
    }

    public function name(): string
    {
        return 'Apollo: Bulk Update Contacts';
    }

    public function description(): string
    {
        return 'Gives several contacts the same values, or applies a different change to each.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'contact_ids' => Field::list('Contact ids', 'For the same change on all of them.'),
                'owner_id' => Field::dynamic('Owner', 'apollo.users'),
                'title' => Field::advanced(Field::text('Job title')),
                'organization_name' => Field::advanced(Field::text('Company name')),
                'account_id' => Field::advanced(Field::dynamic('Account', 'apollo.accounts')),
                'contact_attributes' => Field::advanced(Field::json('Different changes', 'A list of contacts, each with its id and the fields to change. Used instead of the ids.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ids = $this->stringList($config, 'contact_ids');

        if ($ids === [] && empty($config['contact_attributes'])) {
            throw new InvalidArgumentException('Give the contact ids or the different changes.');
        }

        $body = $this->apollo($run, $config, 'POST', '/contacts/bulk_update', [], [...$this->only($config, ['owner_id', 'title', 'organization_name', 'account_id', 'contact_attributes']), 'contact_ids' => $ids ?: null]);

        return $this->listResult($body, 'contacts', $this->shapeContact(...));
    }
}
