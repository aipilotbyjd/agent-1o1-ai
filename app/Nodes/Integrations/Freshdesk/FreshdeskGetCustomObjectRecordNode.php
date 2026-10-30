<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetCustomObjectRecordNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_custom_object_record';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Custom Object Record';
    }

    public function description(): string
    {
        return 'Reads one custom object record.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['schema_id', 'record_id'],
            'properties' => [
                ...$this->credentialFields(),
                'schema_id' => Field::text('Custom object id'),
                'record_id' => Field::text('Record id', 'e.g. BKG-1.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'schema_id', 'The custom object');
        $record = $this->safeId($config, 'record_id', 'The record');

        return $this->findOne($run, $config, "/custom_objects/schemas/{$id}/records/{$record}", $record);
    }
}
