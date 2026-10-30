<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelRefreshPivotTableNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_refresh_pivot_table';
    }

    public function name(): string
    {
        return 'Excel: Refresh Pivot Tables';
    }

    public function description(): string
    {
        return 'Refreshes one pivot table, or every pivot table on the sheet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'pivot_table' => Field::text('Pivot table', 'Its id. Empty refreshes every one on the sheet.', '{00000000-0000-0000-0000-000000000000}'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = $this->sheet($config).'/pivotTables/'.(filled($config['pivot_table'] ?? null) ? $this->nameSegment($config, 'pivot_table', 'The pivot table').'/refresh' : 'refreshAll');
        $this->graph($run, $config, 'POST', $path, [], []);

        return ['refreshed' => true];
    }
}
