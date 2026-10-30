<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelDeleteTableRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_delete_table_row';
    }

    public function name(): string
    {
        return 'Excel: Delete Table Row';
    }

    public function description(): string
    {
        return 'Deletes one row of a table by its place. The rows below move up.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table', 'index'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
                'index' => Field::integer('Row', 'Its place in the table, from 0.', null, 0),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $index = $this->wholeNumber($config, 'index', 'The row', 0);
        $this->graph($run, $config, 'DELETE', $this->table($config)."/rows/{$index}");

        return ['deleted' => true, 'index' => $index];
    }
}
