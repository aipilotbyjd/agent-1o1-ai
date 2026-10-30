<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelAddRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_add_row';
    }

    public function name(): string
    {
        return 'Excel: Add Row';
    }

    public function description(): string
    {
        return 'Writes a row below the last used row of a sheet. An object is matched to the column names; on an empty sheet its keys become the first row.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'values'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'values' => $this->valuesField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->appendRows($run, $config, [$this->decoded($config, 'values', 'Values')]);
    }
}
