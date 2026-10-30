<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelGetTableRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_table_range';
    }

    public function name(): string
    {
        return 'Excel: Get Table Range';
    }

    public function description(): string
    {
        return 'Reads the cells of a table: all of it, the data only, or the header.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
                'part' => Field::select('Part', ['range' => 'The whole table', 'dataBodyRange' => 'The data rows', 'headerRowRange' => 'The header row'], null, 'range'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $part = (string) ($config['part'] ?? 'range');

        if (! in_array($part, ['range', 'dataBodyRange', 'headerRowRange'], true)) {
            throw new InvalidArgumentException('Unknown part of a table.');
        }

        return $this->shapeRange($this->graph($run, $config, 'GET', $this->table($config)."/{$part}"));
    }
}
