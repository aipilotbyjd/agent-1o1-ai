<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelDeleteRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_delete_row';
    }

    public function name(): string
    {
        return 'Excel: Delete Row';
    }

    public function description(): string
    {
        return 'Deletes a row of a sheet by its number. The rows below move up.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->graph($run, $config, 'POST', $this->range($config, "{$number}:{$number}").'/delete', [], ['shift' => 'Up']);

        return ['deleted' => true, 'row' => $number];
    }
}
