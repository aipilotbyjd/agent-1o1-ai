<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateContactOwnersNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_contact_owners';
    }

    public function name(): string
    {
        return 'Apollo: Update Contact Owners';
    }

    public function description(): string
    {
        return 'Gives several contacts a new owner.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_ids', 'owner_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_ids' => Field::list('Contact ids'),
                'owner_id' => Field::dynamic('Owner', 'apollo.users'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = ['contact_ids' => $this->requiredList($config, 'contact_ids', 'The contact ids'), 'owner_id' => $this->requiredString($config, 'owner_id', 'The owner')];

        return $this->listResult($this->apollo($run, $config, 'POST', '/contacts/update_owners', $query), 'contacts', $this->shapeContact(...));
    }
}
