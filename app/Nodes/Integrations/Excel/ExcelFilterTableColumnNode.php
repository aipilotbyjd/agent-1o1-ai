<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelFilterTableColumnNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_filter_table_column';
    }

    public function name(): string
    {
        return 'Excel: Filter Table Column';
    }

    public function description(): string
    {
        return 'Applies a filter to a table column, or clears it when no criteria are given.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table', 'column'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
                'column' => Field::text('Column', 'Its name or id.', 'Status'),
                'criteria' => Field::json('Criteria', 'Graph filter criteria, such as {"filterOn": "custom", "criterion1": ">15", "operator": "and", "criterion2": "<50"}. Empty clears the filter.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = $this->table($config).'/columns/'.$this->nameSegment($config, 'column', 'The column').'/filter';

        if (blank($config['criteria'] ?? null)) {
            $this->graph($run, $config, 'POST', "{$path}/clear", [], []);

            return ['filtered' => false];
        }

        $this->graph($run, $config, 'POST', "{$path}/apply", [], ['criteria' => $this->decoded($config, 'criteria', 'Criteria')]);

        return ['filtered' => true];
    }
}
