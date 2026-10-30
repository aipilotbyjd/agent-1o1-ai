<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelFindOrCreateRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_find_or_create_row';
    }

    public function name(): string
    {
        return 'Excel: Find or Create Row';
    }

    public function description(): string
    {
        return 'Finds the row whose column holds a value, adding a new row when there is none.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'column', 'value', 'values'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'column' => Field::text('Column', 'The name in the first row.', 'Email'),
                'value' => Field::text('Value', null, 'ada@example.com'),
                'values' => $this->valuesField('The row to add when none is found: a list of cell values, or an object keyed by column name.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->matching($this->recordsOf($this->usedRange($run, $config)), $this->requiredString($config, 'column', 'The column'), (string) ($config['value'] ?? ''));

        if ($found !== []) {
            return ['created' => false, 'found' => true, 'row' => $found[0]];
        }

        return ['created' => true, 'found' => false, ...$this->appendRows($run, $config, [$this->decoded($config, 'values', 'Values')])];
    }
}
