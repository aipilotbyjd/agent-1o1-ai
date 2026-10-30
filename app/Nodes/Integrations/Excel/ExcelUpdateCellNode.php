<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelUpdateCellNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_update_cell';
    }

    public function name(): string
    {
        return 'Excel: Update Cell';
    }

    public function description(): string
    {
        return 'Writes a value or a formula to one cell.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'address', 'value'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'address' => Field::text('Cell', 'An A1 address.', 'C10'),
                'value' => Field::text('Value', 'A value, or a formula starting with =.', 'Closed Won'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeRange($this->graph($run, $config, 'PATCH', $this->range($config), [], ['values' => [[$config['value'] ?? '']]]));
    }
}
