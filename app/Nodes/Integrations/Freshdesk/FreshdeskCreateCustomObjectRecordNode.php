<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateCustomObjectRecordNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_custom_object_record';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Custom Object Record';
    }

    public function description(): string
    {
        return 'Adds a record to a custom object.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['schema_id', 'data'],
            'properties' => [
                ...$this->credentialFields(),
                'schema_id' => Field::text('Custom object id'),
                'data' => Field::json('Data (JSON)', 'By field name, e.g. {"customer_name": "Ada"}.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'schema_id', 'The custom object');

        return $this->freshdesk($run, $config, 'POST', "/custom_objects/schemas/{$id}/records", [], ['data' => $this->requiredJson($config, 'data', 'The data')]);
    }
}
