<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListCustomObjectRecordsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_custom_object_records';
    }

    public function name(): string
    {
        return 'Freshdesk: List Custom Object Records';
    }

    public function description(): string
    {
        return 'Lists the records of a custom object.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'schema_id', 'The custom object');
        $rows = array_values((array) ($this->freshdesk($run, $config, 'GET', "/custom_objects/schemas/{$id}/records", ['page_size' => min(100, $this->limit($config, 100))])['records'] ?? []));

        return ['records' => $rows, 'count' => count($rows)];
    }
}
