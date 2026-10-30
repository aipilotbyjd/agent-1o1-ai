<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloFindOrCreateContactNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_find_or_create_contact';
    }

    public function name(): string
    {
        return 'Apollo: Find or Create Contact';
    }

    public function description(): string
    {
        return 'Finds a contact by email, and creates one only when there is none. A contact that exists is left as it is.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->contactFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $match = $this->contactByEmail($run, $config, strtolower($this->requiredString($config, 'email', 'The email')));

        if ($match !== null) {
            return ['created' => false, 'contact' => $this->shapeContact($match)];
        }

        return ['created' => true, 'contact' => $this->shapeContact((array) ($this->apollo($run, $config, 'POST', '/contacts', [], $this->contactBody($config))['contact'] ?? []))];
    }
}
