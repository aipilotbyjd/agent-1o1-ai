<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelDeleteWorkbookNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_delete_workbook';
    }

    public function name(): string
    {
        return 'Excel: Delete Workbook';
    }

    public function description(): string
    {
        return 'Moves a workbook to the recycle bin of its drive.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id'],
            'properties' => [
                ...$this->workbookFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'DELETE', $this->item($config));

        return ['deleted' => true];
    }
}
