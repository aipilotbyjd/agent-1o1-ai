<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelUnprotectWorksheetNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_unprotect_worksheet';
    }

    public function name(): string
    {
        return 'Excel: Unprotect Worksheet';
    }

    public function description(): string
    {
        return 'Takes the protection off a sheet.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'POST', $this->sheet($config).'/protection/unprotect', [], []);

        return ['protected' => false];
    }
}
