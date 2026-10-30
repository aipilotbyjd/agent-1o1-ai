<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelFindWorksheetNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_find_worksheet';
    }

    public function name(): string
    {
        return 'Excel: Find Worksheet';
    }

    public function description(): string
    {
        return 'Finds the sheets of a workbook whose name holds the text.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'name'],
            'properties' => [
                ...$this->workbookFields(),
                'name' => Field::text('Name holds', null, 'Sales'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sheets = (array) ($this->graph($run, $config, 'GET', $this->workbook($config).'/worksheets')['value'] ?? []);
        $wanted = mb_strtolower($this->requiredString($config, 'name', 'The name'));
        $found = array_values(array_filter($sheets, fn (mixed $sheet): bool => is_array($sheet) && str_contains(mb_strtolower((string) ($sheet['name'] ?? '')), $wanted)));

        return ['worksheets' => array_map($this->shapeWorksheet(...), $found), 'count' => count($found), 'found' => $found !== []];
    }
}
