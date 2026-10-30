<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelSetChartDataNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_set_chart_data';
    }

    public function name(): string
    {
        return 'Excel: Set Chart Data';
    }

    public function description(): string
    {
        return 'Points a chart at another range.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'chart', 'source'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'chart' => Field::dynamic('Chart', 'excel.charts', 'Pick a chart, or enter its name.', 'Chart 1', dependsOn: ['workbook_id', 'worksheet'], uses: ['drive_id']),
                'source' => Field::text('Data range', 'An A1 address.', 'A1:C4'),
                'series_by' => Field::select('Series by', ['Auto', 'Columns', 'Rows'], null, 'Auto'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'POST', $this->sheet($config).'/charts/'.$this->nameSegment($config, 'chart', 'The chart').'/setData', [], ['sourceData' => $this->requiredString($config, 'source', 'The data range'), 'seriesBy' => (string) ($config['series_by'] ?? 'Auto')]);

        return ['updated' => true];
    }
}
