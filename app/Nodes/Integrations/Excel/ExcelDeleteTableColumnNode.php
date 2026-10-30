<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelDeleteTableColumnNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_delete_table_column';
    }

    public function name(): string
    {
        return 'Excel: Delete Table Column';
    }

    public function description(): string
    {
        return 'Deletes a column of a table.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table', 'column'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
                'column' => Field::text('Column', 'Its name or id.', 'Status'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'DELETE', $this->table($config).'/columns/'.$this->nameSegment($config, 'column', 'The column'));

        return ['deleted' => true];
    }
}
