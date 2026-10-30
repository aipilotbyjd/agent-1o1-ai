<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetNotebookNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_notebook';
    }

    public function name(): string
    {
        return 'Datadog: Get Notebook';
    }

    public function description(): string
    {
        return 'Gets a notebook with its cells.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $notebook = $this->find($run, $config, '/api/v1/notebooks/'.$this->numericId($config, 'notebook_id', 'The notebook id'));

        return $notebook === null ? ['found' => false] : ['found' => true, ...$this->flatten((array) ($notebook['data'] ?? []))];
    }
}
