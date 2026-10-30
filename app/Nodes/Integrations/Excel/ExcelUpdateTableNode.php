<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelUpdateTableNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_update_table';
    }

    public function name(): string
    {
        return 'Excel: Update Table';
    }

    public function description(): string
    {
        return 'Renames a table, changes its style, or shows or hides its header and totals.';
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
                'name' => Field::text('New name', null, 'Sales'),
                'style' => Field::advanced(Field::text('Style', 'TableStyleLight1 to 21, Medium1 to 28, Dark1 to 11.', 'TableStyleMedium4')),
                'show_headers' => Field::advanced(Field::boolean('Show the header row', null, true)),
                'show_totals' => Field::advanced(Field::boolean('Show the totals row', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter(['name' => filled($config['name'] ?? null) ? (string) $config['name'] : null, 'style' => filled($config['style'] ?? null) ? (string) $config['style'] : null, 'showHeaders' => array_key_exists('show_headers', $config) ? (bool) $config['show_headers'] : null, 'showTotals' => array_key_exists('show_totals', $config) ? (bool) $config['show_totals'] : null], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Give at least one change.');
        }

        return $this->shapeTable($this->graph($run, $config, 'PATCH', $this->table($config), [], $body));
    }
}
