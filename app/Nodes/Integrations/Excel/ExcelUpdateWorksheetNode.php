<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelUpdateWorksheetNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_update_worksheet';
    }

    public function name(): string
    {
        return 'Excel: Update Worksheet';
    }

    public function description(): string
    {
        return 'Renames a sheet, moves it, or hides or shows it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'name' => Field::text('New name', null, 'Sales 2026'),
                'position' => Field::integer('Position', 'From 0, the first sheet.', null, 0),
                'visibility' => Field::select('Visibility', ['Visible', 'Hidden', 'VeryHidden']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter(['name' => filled($config['name'] ?? null) ? (string) $config['name'] : null, 'position' => is_numeric($config['position'] ?? null) ? (int) $config['position'] : null, 'visibility' => filled($config['visibility'] ?? null) ? (string) $config['visibility'] : null], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Give a new name, a position or a visibility.');
        }

        return $this->shapeWorksheet($this->graph($run, $config, 'PATCH', $this->sheet($config), [], $body));
    }
}
