<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelConvertTableToRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_convert_table_to_range';
    }

    public function name(): string
    {
        return 'Excel: Convert Table to Range';
    }

    public function description(): string
    {
        return 'Turns a table back into plain cells, keeping the data.';
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
        return $this->shapeRange($this->graph($run, $config, 'POST', $this->table($config).'/convertToRange', [], []));
    }
}
