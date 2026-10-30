<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloCreateContactNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_create_contact';
    }

    public function name(): string
    {
        return 'Apollo: Create Contact';
    }

    public function description(): string
    {
        return 'Saves a person as a contact. Apollo does not check for duplicates here: use Create or Update Contact for that.';
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
                ...$this->contactFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['contact' => $this->shapeContact((array) ($this->apollo($run, $config, 'POST', '/contacts', [], $this->contactBody($config))['contact'] ?? []))];
    }
}
