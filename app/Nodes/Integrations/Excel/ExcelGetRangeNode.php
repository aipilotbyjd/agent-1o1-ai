<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelGetRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_range';
    }

    public function name(): string
    {
        return 'Excel: Get Range';
    }

    public function description(): string
    {
        return 'Reads the values, text and formulas of a range, or of the whole sheet when the range is empty.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'address' => Field::text('Range', 'An A1 address on the sheet. Empty is everything used.', 'A1:C10'),
                'values_only' => Field::advanced(Field::boolean('Ignore formatted empty cells', 'For the whole sheet: count only cells with values.', true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = filled($config['address'] ?? null) ? $this->range($config) : $this->sheet($config).'/usedRange(valuesOnly='.(($config['values_only'] ?? true) ? 'true' : 'false').')';

        return $this->shapeRange($this->graph($run, $config, 'GET', $path));
    }
}
