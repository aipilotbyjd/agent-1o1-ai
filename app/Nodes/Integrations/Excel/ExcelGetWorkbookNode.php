<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelGetWorkbookNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_workbook';
    }

    public function name(): string
    {
        return 'Excel: Get Workbook';
    }

    public function description(): string
    {
        return 'Reads a workbook\'s file details: name, link, size and last change.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        return $this->shapeItem($this->graph($run, $config, 'GET', $this->item($config)));
    }
}
