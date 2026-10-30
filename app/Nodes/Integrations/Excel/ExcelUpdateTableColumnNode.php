<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelUpdateTableColumnNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_update_table_column';
    }

    public function name(): string
    {
        return 'Excel: Update Table Column';
    }

    public function description(): string
    {
        return 'Writes the values of a table column.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table', 'column', 'values'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
                'column' => Field::text('Column', 'Its name or id.', 'Status'),
                'values' => Field::json('Values', 'A list with the column name first, then one value per row.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $cells = array_map(fn (mixed $cell): array => is_array($cell) ? array_values($cell) : [$cell], array_values($this->decoded($config, 'values', 'Values')));

        return $this->shapeColumn($this->graph($run, $config, 'PATCH', $this->table($config).'/columns/'.$this->nameSegment($config, 'column', 'The column'), [], ['values' => $cells]));
    }
}
