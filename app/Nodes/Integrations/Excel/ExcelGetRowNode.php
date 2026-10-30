<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelGetRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_row';
    }

    public function name(): string
    {
        return 'Excel: Get Row';
    }

    public function description(): string
    {
        return 'Reads one row of a sheet by its number, as a record keyed by the first row.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'row'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'row' => Field::integer('Row number', 'As shown at the left of the sheet.', null, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $number = $this->wholeNumber($config, 'row', 'The row number');

        foreach ($this->recordsOf($this->usedRange($run, $config)) as $record) {
            if ($record['_row'] === $number) {
                return ['found' => true, 'row' => $record];
            }
        }

        return ['found' => false, 'row' => null];
    }
}
