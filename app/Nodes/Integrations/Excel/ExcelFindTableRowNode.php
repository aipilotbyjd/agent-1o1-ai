<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelFindTableRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_find_table_row';
    }

    public function name(): string
    {
        return 'Excel: Find Table Row';
    }

    public function description(): string
    {
        return 'Finds the rows of a table whose column holds a value.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table', 'column', 'value'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
                'column' => Field::text('Column', 'The name in the header.', 'Email'),
                'value' => Field::text('Value', null, 'ada@example.com'),
                'match' => Field::select('Match', ['equals', 'contains'], null, 'equals'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->matching($this->tableRecords($run, $config), $this->requiredString($config, 'column', 'The column'), (string) ($config['value'] ?? ''), (string) ($config['match'] ?? 'equals'));

        return ['rows' => $rows, 'count' => count($rows), 'found' => $rows !== [], 'first' => $rows[0] ?? null];
    }
}
