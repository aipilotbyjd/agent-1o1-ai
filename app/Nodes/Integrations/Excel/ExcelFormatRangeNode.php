<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelFormatRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_format_range';
    }

    public function name(): string
    {
        return 'Excel: Format Range';
    }

    public function description(): string
    {
        return 'Sets the font, fill, alignment, wrapping and sizes of a range.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'address'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'address' => $this->addressField(),
                'bold' => Field::boolean('Bold', null, false),
                'italic' => Field::boolean('Italic', null, false),
                'font_color' => Field::text('Font color', 'An HTML color.', '#FF0000'),
                'font_size' => Field::integer('Font size', null, null, 1),
                'font_name' => Field::advanced(Field::text('Font name', null, 'Calibri')),
                'fill_color' => Field::text('Fill color', 'An HTML color.', '#FFFF00'),
                'horizontal_alignment' => Field::advanced(Field::select('Horizontal alignment', ['General', 'Left', 'Center', 'Right', 'Fill', 'Justify', 'CenterAcrossSelection', 'Distributed'])),
                'vertical_alignment' => Field::advanced(Field::select('Vertical alignment', ['Top', 'Center', 'Bottom', 'Justify', 'Distributed'])),
                'wrap_text' => Field::advanced(Field::boolean('Wrap text', null, false)),
                'column_width' => Field::advanced(Field::integer('Column width', 'In points.', null, 0)),
                'row_height' => Field::advanced(Field::integer('Row height', 'In points.', null, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $range = $this->range($config);
        $font = array_filter(['bold' => array_key_exists('bold', $config) ? (bool) $config['bold'] : null, 'italic' => array_key_exists('italic', $config) ? (bool) $config['italic'] : null, 'color' => filled($config['font_color'] ?? null) ? (string) $config['font_color'] : null, 'size' => is_numeric($config['font_size'] ?? null) ? (float) $config['font_size'] : null, 'name' => filled($config['font_name'] ?? null) ? (string) $config['font_name'] : null], fn (mixed $value): bool => $value !== null);
        $format = array_filter(['horizontalAlignment' => filled($config['horizontal_alignment'] ?? null) ? (string) $config['horizontal_alignment'] : null, 'verticalAlignment' => filled($config['vertical_alignment'] ?? null) ? (string) $config['vertical_alignment'] : null, 'wrapText' => array_key_exists('wrap_text', $config) ? (bool) $config['wrap_text'] : null, 'columnWidth' => is_numeric($config['column_width'] ?? null) ? (float) $config['column_width'] : null, 'rowHeight' => is_numeric($config['row_height'] ?? null) ? (float) $config['row_height'] : null], fn (mixed $value): bool => $value !== null);

        if ($font !== []) {
            $this->graph($run, $config, 'PATCH', "{$range}/format/font", [], $font);
        }

        if (filled($config['fill_color'] ?? null)) {
            $this->graph($run, $config, 'PATCH', "{$range}/format/fill", [], ['color' => (string) $config['fill_color']]);
        }

        if ($format !== []) {
            $this->graph($run, $config, 'PATCH', "{$range}/format", [], $format);
        }

        return ['formatted' => true];
    }
}
