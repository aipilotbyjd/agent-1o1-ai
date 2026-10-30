<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelUpdateTableRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_update_table_row';
    }

    public function name(): string
    {
        return 'Excel: Update Table Row';
    }

    public function description(): string
    {
        return 'Writes to one row of a table by its place. With an object, only the named columns change.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table', 'index', 'values'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
                'index' => Field::integer('Row', 'Its place in the table, from 0.', null, 0),
                'values' => $this->valuesField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $index = $this->wholeNumber($config, 'index', 'The row', 0);
        $values = $this->decoded($config, 'values', 'Values');
        $cells = $this->cellsFor($values, array_is_list($values) ? [] : $this->tableHeader($run, $config));
        $row = $this->graph($run, $config, 'PATCH', $this->table($config)."/rows/{$index}", [], ['values' => [$cells]]);

        return ['index' => $row['index'] ?? $index, 'values' => $row['values'] ?? null];
    }
}
