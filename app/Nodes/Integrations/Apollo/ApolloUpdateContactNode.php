<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateContactNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_contact';
    }

    public function name(): string
    {
        return 'Apollo: Update Contact';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::dynamic('Contact', 'apollo.contacts'),
                ...$this->contactFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['contact' => $this->shapeContact((array) ($this->apollo($run, $config, 'PATCH', '/contacts/'.$this->recordId($config, 'contact_id', 'The contact'), [], $this->contactBody($config))['contact'] ?? []))];
    }
}
