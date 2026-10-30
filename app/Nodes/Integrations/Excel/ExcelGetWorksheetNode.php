<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelGetWorksheetNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_worksheet';
    }

    public function name(): string
    {
        return 'Excel: Get Worksheet';
    }

    public function description(): string
    {
        return 'Reads a sheet\'s details by name or id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeWorksheet($this->graph($run, $config, 'GET', $this->sheet($config)));
    }
}
