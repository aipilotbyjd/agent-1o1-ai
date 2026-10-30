<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelDeleteTableNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_delete_table';
    }

    public function name(): string
    {
        return 'Excel: Delete Table';
    }

    public function description(): string
    {
        return 'Deletes a table and its data.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->graph($run, $config, 'DELETE', $this->table($config));

        return ['deleted' => true];
    }
}
