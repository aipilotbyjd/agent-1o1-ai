<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelReapplyTableFiltersNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_reapply_table_filters';
    }

    public function name(): string
    {
        return 'Excel: Reapply Table Filters';
    }

    public function description(): string
    {
        return 'Applies a table\'s filters again, after its data has changed.';
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
        $this->graph($run, $config, 'POST', $this->table($config).'/reapplyFilters', [], []);

        return ['reapplied' => true];
    }
}
