<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelListNamesNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_list_names';
    }

    public function name(): string
    {
        return 'Excel: List Named Items';
    }

    public function description(): string
    {
        return 'Lists the named ranges and values of a workbook.';
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
        $names = array_map($this->shapeName(...), array_values(array_filter((array) ($this->graph($run, $config, 'GET', $this->workbook($config).'/names')['value'] ?? []), 'is_array')));

        return ['names' => $names, 'count' => count($names)];
    }
}
