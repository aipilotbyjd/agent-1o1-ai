<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloCreateCustomFieldNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_create_custom_field';
    }

    public function name(): string
    {
        return 'Apollo: Create Custom Field';
    }

    public function description(): string
    {
        return 'Adds a custom field to contacts, accounts or deals.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['label', 'modality', 'type'],
            'properties' => [
                ...$this->credentialFields(),
                'label' => Field::text('Label'),
                'modality' => Field::select('On', ['contact' => 'Contacts', 'account' => 'Accounts', 'opportunity' => 'Deals'], null, 'contact'),
                'type' => Field::select('Type', ['string' => 'Text', 'textarea' => 'Long text', 'number' => 'Number', 'date' => 'Date', 'datetime' => 'Date and time', 'boolean' => 'Yes or no'], null, 'string'),
                'meta' => Field::advanced(Field::json('Options', 'Extra settings of the field, as in Apollo\'s API.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->apollo($run, $config, 'POST', '/fields', [], ['label' => $this->requiredString($config, 'label', 'The label'), 'modality' => $this->requiredString($config, 'modality', 'The record kind'), 'type' => $this->requiredString($config, 'type', 'The type'), ...$this->only($config, ['meta'])]);
    }
}
