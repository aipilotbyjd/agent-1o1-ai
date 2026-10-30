<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelDeleteWorksheetNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_delete_worksheet';
    }

    public function name(): string
    {
        return 'Excel: Delete Worksheet';
    }

    public function description(): string
    {
        return 'Deletes a sheet and everything on it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $this->graph($run, $config, 'DELETE', $this->sheet($config));

        return ['deleted' => true];
    }
}
