<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateCustomObjectRecordNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_custom_object_record';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Custom Object Record';
    }

    public function description(): string
    {
        return 'Changes a custom object record.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['schema_id', 'record_id', 'data'],
            'properties' => [
                ...$this->credentialFields(),
                'schema_id' => Field::text('Custom object id'),
                'record_id' => Field::text('Record id', 'e.g. BKG-1.'),
                'data' => Field::json('Data (JSON)', 'By field name, e.g. {"customer_name": "Ada"}.'),
                'version' => Field::advanced(Field::integer('Version', 'The record\'s current version, if it is required.', null)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'schema_id', 'The custom object');
        $record = $this->safeId($config, 'record_id', 'The record');

        return $this->freshdesk($run, $config, 'PUT', "/custom_objects/schemas/{$id}/records/{$record}", [], $this->present(['display_id' => $record, 'data' => $this->requiredJson($config, 'data', 'The data'), 'version' => $this->number($config, 'version')]));
    }
}
