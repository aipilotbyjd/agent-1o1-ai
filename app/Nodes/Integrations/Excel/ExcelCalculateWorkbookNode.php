<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelCalculateWorkbookNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_calculate_workbook';
    }

    public function name(): string
    {
        return 'Excel: Recalculate Workbook';
    }

    public function description(): string
    {
        return 'Recalculates the workbook\'s formulas.';
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
                'calculation_type' => Field::select('Calculation', ['Recalculate' => 'Changed cells', 'Full' => 'All cells', 'FullRebuild' => 'All cells, rebuilding dependencies'], null, 'Recalculate'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->graph($run, $config, 'POST', $this->workbook($config).'/application/calculate', [], ['calculationType' => (string) ($config['calculation_type'] ?? 'Recalculate')]);

        return ['calculated' => true];
    }
}
