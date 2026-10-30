<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ApolloUpsertContactNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_upsert_contact';
    }

    public function name(): string
    {
        return 'Apollo: Create or Update Contact';
    }

    public function description(): string
    {
        return 'Saves a person as a contact, or updates the one Apollo matches on CRM id, email, or name and company. Values you send overwrite what is stored.';
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
        return ['contact' => $this->shapeContact((array) ($this->apollo($run, $config, 'POST', '/contacts', [], [...$this->contactBody($config), 'run_dedupe' => true])['contact'] ?? []))];
    }
}
