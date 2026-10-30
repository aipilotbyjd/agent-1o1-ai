<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelListChartsNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_list_charts';
    }

    public function name(): string
    {
        return 'Excel: List Charts';
    }

    public function description(): string
    {
        return 'Lists the charts on a sheet.';
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
        $charts = array_map($this->shapeChart(...), array_values(array_filter((array) ($this->graph($run, $config, 'GET', $this->sheet($config).'/charts')['value'] ?? []), 'is_array')));

        return ['charts' => $charts, 'count' => count($charts)];
    }
}
