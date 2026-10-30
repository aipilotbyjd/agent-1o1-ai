<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelGetChartImageNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_chart_image';
    }

    public function name(): string
    {
        return 'Excel: Get Chart Image';
    }

    public function description(): string
    {
        return 'Renders a chart as a base64 image.';
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
                'width' => Field::advanced(Field::integer('Width', 'In pixels. Empty keeps its size.', null, 1)),
                'height' => Field::advanced(Field::integer('Height', 'In pixels. Empty keeps its size.', null, 1)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $size = implode(',', array_filter(['width='.(is_numeric($config['width'] ?? null) ? (int) $config['width'] : ''), 'height='.(is_numeric($config['height'] ?? null) ? (int) $config['height'] : '')], fn (string $part): bool => ! str_ends_with($part, '=')));
        $path = $this->sheet($config).'/charts/'.$this->nameSegment($config, 'chart', 'The chart').'/image'.($size !== '' ? "({$size})" : '');

        return ['image_base64' => $this->graph($run, $config, 'GET', $path)['value'] ?? null];
    }
}
