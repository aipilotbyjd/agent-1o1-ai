<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelSortRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_sort_range';
    }

    public function name(): string
    {
        return 'Excel: Sort Range';
    }

    public function description(): string
    {
        return 'Sorts a range by one or more of its columns.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'address', 'fields'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'address' => $this->addressField(),
                'fields' => Field::json('Sort by', 'A list such as [{"key": 0, "ascending": true}], where key is the column\'s place in the range, from 0.', 'array'),
                'has_headers' => Field::boolean('First row is a header', null, false),
                'match_case' => Field::advanced(Field::boolean('Match case', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'POST', $this->range($config).'/sort/apply', [], ['fields' => array_values($this->decoded($config, 'fields', 'Sort by')), 'hasHeaders' => (bool) ($config['has_headers'] ?? false), 'matchCase' => (bool) ($config['match_case'] ?? false)]);

        return ['sorted' => true];
    }
}
