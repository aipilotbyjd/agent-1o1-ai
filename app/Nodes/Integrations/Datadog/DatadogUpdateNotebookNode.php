<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpdateNotebookNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_notebook';
    }

    public function name(): string
    {
        return 'Datadog: Update Notebook';
    }

    public function description(): string
    {
        return 'Changes the settings filled in here on a notebook; the rest stays as it is.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['notebook_id'],
            'properties' => [
                ...$this->credentialFields(),
                'notebook_id' => Field::text('Notebook id'),
                'name' => Field::text('Name'),
                'cells' => Field::json('Cells', 'Replaces every cell.', 'array'),
                'status' => Field::advanced(Field::text('Status', null, 'published')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'notebook_id', 'The notebook id');
        $current = (array) data_get($this->datadog($run, $config, 'GET', "/api/v1/notebooks/{$id}"), 'data.attributes', []);
        $attributes = [...array_intersect_key($current, array_flip(['name', 'cells', 'time', 'status', 'metadata'])), ...array_filter(['name' => $this->text($config, 'name'), 'cells' => $this->jsonOf($config, 'cells', 'Cells'), 'status' => $this->text($config, 'status')], fn (mixed $value): bool => $value !== null)];

        return $this->flatten((array) ($this->datadog($run, $config, 'PUT', "/api/v1/notebooks/{$id}", [], ['data' => ['type' => 'notebooks', 'attributes' => $attributes]])['data'] ?? []));
    }
}
