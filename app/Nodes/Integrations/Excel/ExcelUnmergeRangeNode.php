<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelUnmergeRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_unmerge_range';
    }

    public function name(): string
    {
        return 'Excel: Unmerge Cells';
    }

    public function description(): string
    {
        return 'Splits the merged cells of a range.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'POST', $this->range($config).'/unmerge', [], []);

        return ['merged' => false];
    }
}
