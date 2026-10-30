<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelInsertRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_insert_range';
    }

    public function name(): string
    {
        return 'Excel: Insert Cells';
    }

    public function description(): string
    {
        return 'Inserts empty cells at a range, moving the others down or to the right.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'address', 'shift'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'address' => $this->addressField(),
                'shift' => Field::select('Move cells', ['Down', 'Right'], null, 'Down'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeRange($this->graph($run, $config, 'POST', $this->range($config).'/insert', [], ['shift' => (string) ($config['shift'] ?? 'Down')]));
    }
}
