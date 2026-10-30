<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelGetTableNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_table';
    }

    public function name(): string
    {
        return 'Excel: Get Table';
    }

    public function description(): string
    {
        return 'Reads a table\'s details.';
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
        return $this->shapeTable($this->graph($run, $config, 'GET', $this->table($config)));
    }
}
