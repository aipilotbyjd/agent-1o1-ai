<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelProtectWorksheetNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_protect_worksheet';
    }

    public function name(): string
    {
        return 'Excel: Protect Worksheet';
    }

    public function description(): string
    {
        return 'Protects a sheet from changes, except what the options allow.';
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
                'options' => Field::advanced(Field::json('Options', 'What stays allowed, as Graph names it: allowFormatCells, allowInsertRows, allowSort and so on.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $options = (array) (is_string($config['options'] ?? null) ? json_decode($config['options'], true) : ($config['options'] ?? []));
        $this->graph($run, $config, 'POST', $this->sheet($config).'/protection/protect', [], $options !== [] ? ['options' => $options] : []);

        return ['protected' => true];
    }
}
