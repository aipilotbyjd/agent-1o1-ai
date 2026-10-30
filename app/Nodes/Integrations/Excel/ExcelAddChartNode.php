<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelAddChartNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_add_chart';
    }

    public function name(): string
    {
        return 'Excel: Add Chart';
    }

    public function description(): string
    {
        return 'Adds a chart for a range to a sheet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'chart_type', 'source'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'chart_type' => Field::text('Chart type', 'As Excel names it: ColumnClustered, Line, Pie, BarClustered and so on.', 'ColumnClustered'),
                'source' => Field::text('Data range', 'An A1 address.', 'A1:C4'),
                'series_by' => Field::select('Series by', ['Auto', 'Columns', 'Rows'], null, 'Auto'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeChart($this->graph($run, $config, 'POST', $this->sheet($config).'/charts/add', [], ['type' => $this->requiredString($config, 'chart_type', 'The chart type'), 'sourceData' => $this->requiredString($config, 'source', 'The data range'), 'seriesBy' => (string) ($config['series_by'] ?? 'Auto')]));
    }
}
