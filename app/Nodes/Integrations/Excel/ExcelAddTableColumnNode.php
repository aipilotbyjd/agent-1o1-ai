<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelAddTableColumnNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_add_table_column';
    }

    public function name(): string
    {
        return 'Excel: Add Table Column';
    }

    public function description(): string
    {
        return 'Adds a column to a table. The first value is its name; the list must fit the table\'s rows.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table', 'values'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
                'values' => Field::json('Values', 'A list with the column name first, then one value per row, such as ["Status", "Open", "Closed"].', 'array'),
                'index' => Field::advanced(Field::integer('Insert at', 'A place in the table, from 0. Empty adds at the end.', null, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $cells = array_map(fn (mixed $cell): array => is_array($cell) ? array_values($cell) : [$cell], array_values($this->decoded($config, 'values', 'Values')));

        return $this->shapeColumn($this->graph($run, $config, 'POST', $this->table($config).'/columns', [], ['values' => $cells, 'index' => is_numeric($config['index'] ?? null) ? (int) $config['index'] : null]));
    }
}
