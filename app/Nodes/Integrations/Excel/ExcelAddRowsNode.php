<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelAddRowsNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_add_rows';
    }

    public function name(): string
    {
        return 'Excel: Add Rows';
    }

    public function description(): string
    {
        return 'Writes several rows below the last used row of a sheet. Each row is a list of cell values or an object keyed by column name.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'rows'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'rows' => Field::json('Rows', 'A list of rows.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = array_values($this->decoded($config, 'rows', 'Rows'));

        if (array_filter($rows, 'is_array') !== $rows) {
            throw new InvalidArgumentException('Each row must be a list of cell values or an object keyed by column name.');
        }

        return $this->appendRows($run, $config, $rows);
    }
}
