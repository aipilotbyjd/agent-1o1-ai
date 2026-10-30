<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateNotebookNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_notebook';
    }

    public function name(): string
    {
        return 'Datadog: Create Notebook';
    }

    public function description(): string
    {
        return 'Creates a notebook, from text or from cells.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'text' => Field::textarea('Text', 'Markdown for one cell.'),
                'live_span' => Field::advanced(Field::text('Time span', 'Like 1h, 1d or 1w.', '1h')),
                'cells' => Field::advanced(Field::json('Cells', 'As in the API. Used instead of Text.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $cells = $this->jsonOf($config, 'cells', 'Cells') ?? [['type' => 'notebook_cells', 'attributes' => ['definition' => ['type' => 'markdown', 'text' => $this->text($config, 'text') ?? '']]]];
        $attributes = ['name' => $this->requiredString($config, 'name', 'The name'), 'cells' => $cells, 'time' => ['live_span' => $this->text($config, 'live_span') ?? '1h'], 'status' => 'published'];

        return $this->flatten((array) ($this->datadog($run, $config, 'POST', '/api/v1/notebooks', [], ['data' => ['type' => 'notebooks', 'attributes' => $attributes]])['data'] ?? []));
    }
}
