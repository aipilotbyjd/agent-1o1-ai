<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelAddTableNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_add_table';
    }

    public function name(): string
    {
        return 'Excel: Add Table';
    }

    public function description(): string
    {
        return 'Turns a range into a table.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'address'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'address' => $this->addressField(),
                'has_headers' => Field::boolean('First row holds the column names', null, true),
                'name' => Field::text('Name', 'Empty lets Excel choose one.', 'Sales'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $table = $this->graph($run, $config, 'POST', $this->sheet($config).'/tables/add', [], ['address' => $this->requiredString($config, 'address', 'The range'), 'hasHeaders' => (bool) ($config['has_headers'] ?? true)]);

        if (filled($config['name'] ?? null) && isset($table['id'])) {
            $table = $this->graph($run, $config, 'PATCH', $this->workbook($config).'/tables/'.rawurlencode((string) $table['id']), [], ['name' => (string) $config['name']]);
        }

        return $this->shapeTable($table);
    }
}
