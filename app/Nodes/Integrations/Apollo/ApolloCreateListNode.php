<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloCreateListNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_create_list';
    }

    public function name(): string
    {
        return 'Apollo: Create List';
    }

    public function description(): string
    {
        return 'Creates a list of contacts or of accounts.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'modality'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'modality' => Field::select('Holds', ['contacts' => 'Contacts', 'accounts' => 'Accounts'], null, 'contacts'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'POST', '/labels', [], ['name' => $this->requiredString($config, 'name', 'The name'), 'modality' => $this->requiredString($config, 'modality', 'What it holds')]);

        return ['list' => $body['label'] ?? $body];
    }
}
