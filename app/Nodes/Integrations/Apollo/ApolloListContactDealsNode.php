<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloListContactDealsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_contact_deals';
    }

    public function name(): string
    {
        return 'Apollo: List Contact Deals';
    }

    public function description(): string
    {
        return 'Lists the deals linked to a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::dynamic('Contact', 'apollo.contacts'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'POST', '/contacts/'.$this->recordId($config, 'contact_id', 'The contact').'/opportunities'), 'opportunities', $this->shapeDeal(...));
    }
}
