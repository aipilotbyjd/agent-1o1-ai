<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomUpdateDataAttributeNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_update_data_attribute';
    }

    public function name(): string
    {
        return 'Intercom: Update Data Attribute';
    }

    public function description(): string
    {
        return 'Changes a data attribute\'s description, or archives it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['data_attribute_id'],
            'properties' => [
                ...$this->credentialFields(),
                'data_attribute_id' => Field::text('Attribute id'),
                'description' => Field::advanced(Field::text('Description')),
                'archived' => Field::advanced(Field::boolean('Archived')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'data_attribute_id', 'The attribute');
        $body = $this->present(['description' => $this->text($config, 'description'), 'archived' => $this->flag($config, 'archived')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->intercom($run, $config, 'PUT', "/data_attributes/{$id}", [], $body);
    }
}
