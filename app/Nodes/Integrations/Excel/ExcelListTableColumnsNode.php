<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelListTableColumnsNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_list_table_columns';
    }

    public function name(): string
    {
        return 'Excel: List Table Columns';
    }

    public function description(): string
    {
        return 'Lists the columns of a table with their values.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $columns = array_map($this->shapeColumn(...), array_values(array_filter((array) ($this->graph($run, $config, 'GET', $this->table($config).'/columns')['value'] ?? []), 'is_array')));

        return ['columns' => $columns, 'count' => count($columns)];
    }
}
