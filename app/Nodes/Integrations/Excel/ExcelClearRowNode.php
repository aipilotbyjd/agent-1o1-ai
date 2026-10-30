<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelClearRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_clear_row';
    }

    public function name(): string
    {
        return 'Excel: Clear Row';
    }

    public function description(): string
    {
        return 'Empties the cells of a row by its number, leaving the row in place.';
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
        $this->graph($run, $config, 'POST', $this->range($config, "{$number}:{$number}").'/clear', [], ['applyTo' => 'Contents']);

        return ['cleared' => true, 'row' => $number];
    }
}
