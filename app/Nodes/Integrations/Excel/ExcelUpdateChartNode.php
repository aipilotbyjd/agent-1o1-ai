<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelUpdateChartNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_update_chart';
    }

    public function name(): string
    {
        return 'Excel: Update Chart';
    }

    public function description(): string
    {
        return 'Renames, moves or resizes a chart.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'name' => Field::text('New name'),
                'height' => Field::advanced(Field::integer('Height', 'In points.', null, 0)),
                'width' => Field::advanced(Field::integer('Width', 'In points.', null, 0)),
                'left' => Field::advanced(Field::integer('Left', 'In points.', null, 0)),
                'top' => Field::advanced(Field::integer('Top', 'In points.', null, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter(['name' => filled($config['name'] ?? null) ? (string) $config['name'] : null, 'height' => is_numeric($config['height'] ?? null) ? (float) $config['height'] : null, 'width' => is_numeric($config['width'] ?? null) ? (float) $config['width'] : null, 'left' => is_numeric($config['left'] ?? null) ? (float) $config['left'] : null, 'top' => is_numeric($config['top'] ?? null) ? (float) $config['top'] : null], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Give at least one change.');
        }

        return $this->shapeChart($this->graph($run, $config, 'PATCH', $this->sheet($config).'/charts/'.$this->nameSegment($config, 'chart', 'The chart'), [], $body));
    }
}
