<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelAddTableRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_add_table_row';
    }

    public function name(): string
    {
        return 'Excel: Add Table Row';
    }

    public function description(): string
    {
        return 'Adds one or more rows to a table. Each row is a list of cell values or an object keyed by column name.';
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
                'values' => Field::json('Values', 'One row, or a list of rows: lists of cell values, or objects keyed by column name.'),
                'index' => Field::advanced(Field::integer('Insert at', 'A place in the table, from 0. Empty adds at the end.', null, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $values = $this->decoded($config, 'values', 'Values');
        $rows = array_is_list($values) && is_array($values[0] ?? null) ? $values : [$values];
        $header = array_filter($rows, fn (array $row): bool => ! array_is_list($row)) !== [] ? $this->tableHeader($run, $config) : [];
        $body = ['values' => array_map(fn (array $row): array => $this->cellsFor($row, $header), $rows), 'index' => is_numeric($config['index'] ?? null) ? (int) $config['index'] : null];
        $row = $this->graph($run, $config, 'POST', $this->table($config).'/rows', [], $body);

        return ['index' => $row['index'] ?? null, 'values' => $row['values'] ?? null, 'rows_added' => count($rows)];
    }
}
