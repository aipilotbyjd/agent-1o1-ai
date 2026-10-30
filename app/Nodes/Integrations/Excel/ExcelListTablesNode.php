<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelListTablesNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_list_tables';
    }

    public function name(): string
    {
        return 'Excel: List Tables';
    }

    public function description(): string
    {
        return 'Lists the tables of a workbook, or of one sheet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => Field::advanced(Field::dynamic('Worksheet', 'excel.worksheets', 'Empty lists the whole workbook.', 'Sheet1', dependsOn: ['workbook_id'], uses: ['drive_id'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = filled($config['worksheet'] ?? null) ? $this->sheet($config).'/tables' : $this->workbook($config).'/tables';
        $tables = array_map($this->shapeTable(...), array_values(array_filter((array) ($this->graph($run, $config, 'GET', $path)['value'] ?? []), 'is_array')));

        return ['tables' => $tables, 'count' => count($tables)];
    }
}
