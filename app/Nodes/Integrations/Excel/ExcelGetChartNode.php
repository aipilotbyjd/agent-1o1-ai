<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelGetChartNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_chart';
    }

    public function name(): string
    {
        return 'Excel: Get Chart';
    }

    public function description(): string
    {
        return 'Reads a chart\'s details.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'chart'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'chart' => Field::dynamic('Chart', 'excel.charts', 'Pick a chart, or enter its name.', 'Chart 1', dependsOn: ['workbook_id', 'worksheet'], uses: ['drive_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeChart($this->graph($run, $config, 'GET', $this->sheet($config).'/charts/'.$this->nameSegment($config, 'chart', 'The chart')));
    }
}
