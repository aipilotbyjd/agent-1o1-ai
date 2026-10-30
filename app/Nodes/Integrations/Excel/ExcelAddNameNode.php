<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelAddNameNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_add_name';
    }

    public function name(): string
    {
        return 'Excel: Add Named Item';
    }

    public function description(): string
    {
        return 'Names a range or a formula.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'name', 'reference'],
            'properties' => [
                ...$this->workbookFields(),
                'name' => Field::text('Name', null, 'TaxRate'),
                'reference' => Field::text('Refers to', 'A range or a formula.', '=Sheet1!$A$1'),
                'comment' => Field::text('Comment'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeName($this->graph($run, $config, 'POST', $this->workbook($config).'/names/add', [], array_filter(['name' => $this->requiredString($config, 'name', 'The name'), 'reference' => $this->requiredString($config, 'reference', 'The reference'), 'comment' => filled($config['comment'] ?? null) ? (string) $config['comment'] : null])));
    }
}
