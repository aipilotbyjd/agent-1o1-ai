<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogDeleteNotebookNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_notebook';
    }

    public function name(): string
    {
        return 'Datadog: Delete Notebook';
    }

    public function description(): string
    {
        return 'Deletes a notebook.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['notebook_id'],
            'properties' => [
                ...$this->credentialFields(),
                'notebook_id' => Field::text('Notebook id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'notebook_id', 'The notebook id');
        $this->datadog($run, $config, 'DELETE', "/api/v1/notebooks/{$id}");

        return ['deleted' => true, 'id' => $id];
    }
}
