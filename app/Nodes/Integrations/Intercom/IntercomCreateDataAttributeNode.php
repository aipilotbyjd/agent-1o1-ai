<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomCreateDataAttributeNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_create_data_attribute';
    }

    public function name(): string
    {
        return 'Intercom: Create Data Attribute';
    }

    public function description(): string
    {
        return 'Adds a custom data attribute to contacts or companies.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'model', 'data_type'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'plan'),
                'model' => Field::select('On', ['contact' => 'Contacts', 'company' => 'Companies'], null, 'contact'),
                'data_type' => Field::select('Type', ['string' => 'Text', 'integer' => 'Whole number', 'float' => 'Number', 'boolean' => 'Yes or no', 'date' => 'Date'], null, 'string'),
                'description' => Field::advanced(Field::text('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->intercom($run, $config, 'POST', '/data_attributes', [], $this->present(['name' => $this->requiredString($config, 'name', 'The name'), 'model' => $config['model'] ?? 'contact', 'data_type' => $config['data_type'] ?? 'string', 'description' => $this->text($config, 'description')]));
    }
}
