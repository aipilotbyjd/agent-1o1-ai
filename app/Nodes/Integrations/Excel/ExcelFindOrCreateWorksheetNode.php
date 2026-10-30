<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelFindOrCreateWorksheetNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_find_or_create_worksheet';
    }

    public function name(): string
    {
        return 'Excel: Find or Create Worksheet';
    }

    public function description(): string
    {
        return 'Returns the sheet with this exact name, adding it when there is none.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'name'],
            'properties' => [
                ...$this->workbookFields(),
                'name' => Field::text('Name', null, 'Sales'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->requiredString($config, 'name', 'The name');

        foreach ((array) ($this->graph($run, $config, 'GET', $this->workbook($config).'/worksheets')['value'] ?? []) as $sheet) {
            if (is_array($sheet) && mb_strtolower((string) ($sheet['name'] ?? '')) === mb_strtolower($name)) {
                return [...$this->shapeWorksheet($sheet), 'created' => false];
            }
        }

        return [...$this->shapeWorksheet($this->graph($run, $config, 'POST', $this->workbook($config).'/worksheets/add', [], ['name' => $name])), 'created' => true];
    }
}
