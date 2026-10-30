<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelGetCellNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_cell';
    }

    public function name(): string
    {
        return 'Excel: Get Cell';
    }

    public function description(): string
    {
        return 'Reads one cell by its row and column number.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'row', 'column'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'row' => Field::integer('Row', 'From 1.', null, 1),
                'column' => Field::integer('Column', 'From 1, the column A.', null, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $row = $this->wholeNumber($config, 'row', 'The row') - 1;
        $column = $this->wholeNumber($config, 'column', 'The column') - 1;
        $cell = $this->graph($run, $config, 'GET', $this->sheet($config)."/cell(row={$row},column={$column})");

        return [...$this->shapeRange($cell), 'value' => $cell['values'][0][0] ?? null];
    }
}
