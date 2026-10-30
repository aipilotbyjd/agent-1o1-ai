<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelSortTableNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_sort_table';
    }

    public function name(): string
    {
        return 'Excel: Sort Table';
    }

    public function description(): string
    {
        return 'Sorts a table by one or more columns, or clears the sort.';
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
                'fields' => Field::json('Sort by', 'A list such as [{"key": 0, "ascending": true}], where key is the column\'s place, from 0. Empty clears the sort.', 'array'),
                'match_case' => Field::advanced(Field::boolean('Match case', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        if (blank($config['fields'] ?? null)) {
            $this->graph($run, $config, 'POST', $this->table($config).'/sort/clear', [], []);

            return ['sorted' => false];
        }

        $this->graph($run, $config, 'POST', $this->table($config).'/sort/apply', [], ['fields' => array_values($this->decoded($config, 'fields', 'Sort by')), 'matchCase' => (bool) ($config['match_case'] ?? false)]);

        return ['sorted' => true];
    }
}
