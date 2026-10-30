<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelUpdateRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_update_row';
    }

    public function name(): string
    {
        return 'Excel: Update Row';
    }

    public function description(): string
    {
        return 'Writes to one row of a sheet by its number. With an object, only the named columns change.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'row', 'values'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'row' => Field::integer('Row number', 'As shown at the left of the sheet.', null, 1),
                'values' => $this->valuesField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $number = $this->wholeNumber($config, 'row', 'The row number');
        $used = $this->usedRange($run, array_diff_key($config, ['address' => true]));
        [$firstColumn] = self::bounds((string) ($used['address'] ?? 'A1'));
        $cells = $this->cellsFor($this->decoded($config, 'values', 'Values'), $this->headerOf($used));
        $address = self::columnLetters($firstColumn).$number.':'.self::columnLetters($firstColumn + count($cells) - 1).$number;

        return $this->shapeRange($this->graph($run, $config, 'PATCH', $this->range($config, $address), [], ['values' => [$cells]]));
    }
}
