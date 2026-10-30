<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelGetTableRowNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_table_row';
    }

    public function name(): string
    {
        return 'Excel: Get Table Row';
    }

    public function description(): string
    {
        return 'Reads one row of a table by its place, as a record keyed by the header.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table', 'index'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
                'index' => Field::integer('Row', 'Its place in the table, from 0.', null, 0),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $index = $this->wholeNumber($config, 'index', 'The row', 0);
        $row = $this->graph($run, $config, 'GET', $this->table($config)."/rows/itemAt(index={$index})");
        $cells = array_values((array) (array_values((array) ($row['values'] ?? []))[0] ?? []));
        $header = $this->tableHeader($run, $config);

        return ['_index' => (int) ($row['index'] ?? $index), ...array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), null))];
    }
}
