<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelListPivotTablesNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_list_pivot_tables';
    }

    public function name(): string
    {
        return 'Excel: List Pivot Tables';
    }

    public function description(): string
    {
        return 'Lists the pivot tables on a sheet.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tables = array_map(fn (array $table): array => ['id' => $table['id'] ?? null, 'name' => $table['name'] ?? null], array_values(array_filter((array) ($this->graph($run, $config, 'GET', $this->sheet($config).'/pivotTables')['value'] ?? []), 'is_array')));

        return ['pivot_tables' => $tables, 'count' => count($tables)];
    }
}
