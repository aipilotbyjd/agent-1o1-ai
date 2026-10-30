<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelDeleteChartNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_delete_chart';
    }

    public function name(): string
    {
        return 'Excel: Delete Chart';
    }

    public function description(): string
    {
        return 'Deletes a chart.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->graph($run, $config, 'DELETE', $this->sheet($config).'/charts/'.$this->nameSegment($config, 'chart', 'The chart'));

        return ['deleted' => true];
    }
}
