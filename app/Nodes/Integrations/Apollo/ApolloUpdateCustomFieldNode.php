<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateCustomFieldNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_custom_field';
    }

    public function name(): string
    {
        return 'Apollo: Update Custom Field';
    }

    public function description(): string
    {
        return 'Renames a custom field or changes its settings.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['field_id'],
            'properties' => [
                ...$this->credentialFields(),
                'field_id' => Field::text('Field id'),
                'label' => Field::text('Label'),
                'meta' => Field::advanced(Field::json('Options', 'Extra settings of the field, as in Apollo\'s API.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->apollo($run, $config, 'PATCH', '/fields', [], ['fields' => [['id' => $this->recordId($config, 'field_id', 'The field id'), ...$this->only($config, ['label', 'meta'])]]]);
    }
}
