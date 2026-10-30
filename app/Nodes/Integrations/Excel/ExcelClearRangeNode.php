<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelClearRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_clear_range';
    }

    public function name(): string
    {
        return 'Excel: Clear Range';
    }

    public function description(): string
    {
        return 'Clears the contents, the formatting or both of a range.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'address'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'address' => $this->addressField(),
                'apply_to' => Field::select('Clear', ['All', 'Contents', 'Formats'], null, 'Contents'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'POST', $this->range($config).'/clear', [], ['applyTo' => (string) ($config['apply_to'] ?? 'Contents')]);

        return ['cleared' => true];
    }
}
