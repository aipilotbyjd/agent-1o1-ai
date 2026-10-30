<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelAddWorksheetNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_add_worksheet';
    }

    public function name(): string
    {
        return 'Excel: Add Worksheet';
    }

    public function description(): string
    {
        return 'Adds a sheet to a workbook.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id'],
            'properties' => [
                ...$this->workbookFields(),
                'name' => Field::text('Name', 'Empty lets Excel choose one. Up to 31 characters.', 'Sales'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeWorksheet($this->graph($run, $config, 'POST', $this->workbook($config).'/worksheets/add', [], array_filter(['name' => filled($config['name'] ?? null) ? (string) $config['name'] : null])));
    }
}
