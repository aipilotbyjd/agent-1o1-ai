<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelDeleteRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_delete_range';
    }

    public function name(): string
    {
        return 'Excel: Delete Cells';
    }

    public function description(): string
    {
        return 'Deletes the cells of a range, moving the others up or to the left.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
                'shift' => Field::select('Move cells', ['Up', 'Left'], null, 'Up'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'POST', $this->range($config).'/delete', [], ['shift' => (string) ($config['shift'] ?? 'Up')]);

        return ['deleted' => true];
    }
}
