<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelGetNameRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_name_range';
    }

    public function name(): string
    {
        return 'Excel: Read Named Range';
    }

    public function description(): string
    {
        return 'Reads the cells of a named range.';
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
        return $this->shapeRange($this->graph($run, $config, 'GET', $this->workbook($config).'/names/'.$this->nameSegment($config, 'name', 'The name').'/range'));
    }
}
