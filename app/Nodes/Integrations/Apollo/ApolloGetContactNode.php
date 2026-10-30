<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetContactNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_contact';
    }

    public function name(): string
    {
        return 'Apollo: Get Contact';
    }

    public function description(): string
    {
        return 'Reads one contact by its id.';
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
        return ['contact' => $this->shapeContact((array) ($this->apollo($run, $config, 'GET', '/contacts/'.$this->recordId($config, 'contact_id', 'The contact'))['contact'] ?? []))];
    }
}
