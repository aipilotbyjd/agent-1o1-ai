<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelClearTableFiltersNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_clear_table_filters';
    }

    public function name(): string
    {
        return 'Excel: Clear Table Filters';
    }

    public function description(): string
    {
        return 'Clears every filter on a table.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'POST', $this->table($config).'/clearFilters', [], []);

        return ['cleared' => true];
    }
}
