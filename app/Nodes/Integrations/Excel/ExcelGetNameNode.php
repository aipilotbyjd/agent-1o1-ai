<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelGetNameNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_name';
    }

    public function name(): string
    {
        return 'Excel: Get Named Item';
    }

    public function description(): string
    {
        return 'Reads a named item.';
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
                'name' => Field::dynamic('Name', 'excel.names', 'Pick a named item, or enter its name.', 'TaxRate', dependsOn: ['workbook_id'], uses: ['drive_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeName($this->graph($run, $config, 'GET', $this->workbook($config).'/names/'.$this->nameSegment($config, 'name', 'The name')));
    }
}
