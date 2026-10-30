<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomSearchContactsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_search_contacts';
    }

    public function name(): string
    {
        return 'Intercom: Search Contacts';
    }

    public function description(): string
    {
        return 'Finds contacts by a field, such as email, name, role or a custom attribute.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['field', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'field' => Field::text('Field', 'e.g. email, name, role, phone, external_id, custom_attributes.plan.', 'email'),
                'operator' => Field::advanced(Field::select('Match', ['=' => 'Is', '!=' => 'Is not', '~' => 'Contains', '!~' => 'Does not contain', '^' => 'Starts with', '$' => 'Ends with', '>' => 'Greater than', '<' => 'Less than'], null, '=')),
                'value' => Field::text('Value', null, 'ada@example.com'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $field = $this->requiredString($config, 'field', 'The field');
        $rows = $this->cursorAll($run, $config, '/contacts/search', 'data', [], ['field' => $field, 'operator' => trim((string) ($config['operator'] ?? '=')), 'value' => $this->requiredString($config, 'value', 'The value')], $this->limit($config, 50));

        return ['contacts' => array_map($this->shapeContact(...), $rows), 'count' => count($rows)];
    }
}
