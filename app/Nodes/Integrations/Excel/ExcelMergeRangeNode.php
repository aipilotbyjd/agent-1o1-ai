<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelMergeRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_merge_range';
    }

    public function name(): string
    {
        return 'Excel: Merge Cells';
    }

    public function description(): string
    {
        return 'Merges the cells of a range.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'across' => Field::boolean('Merge each row separately', null, false),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'POST', $this->range($config).'/merge', [], ['across' => (bool) ($config['across'] ?? false)]);

        return ['merged' => true];
    }
}
