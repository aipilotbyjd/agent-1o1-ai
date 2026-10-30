<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetCustomObjectSchemaNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_custom_object_schema';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Custom Object';
    }

    public function description(): string
    {
        return 'Reads a custom object schema and its fields.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['schema_id'],
            'properties' => [
                ...$this->credentialFields(),
                'schema_id' => Field::text('Custom object id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'schema_id', 'The custom object');

        return $this->findOne($run, $config, "/custom_objects/schemas/{$id}", $id);
    }
}
