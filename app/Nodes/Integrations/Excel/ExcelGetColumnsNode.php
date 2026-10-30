<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelGetColumnsNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_get_columns';
    }

    public function name(): string
    {
        return 'Excel: Get Columns';
    }

    public function description(): string
    {
        return 'Reads the values of chosen columns of a sheet, by column name or letter.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'columns'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'columns' => Field::list('Columns', 'Column names from the first row, or letters such as C.', 'Email'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $wanted = is_string($config['columns'] ?? null) ? explode(',', $config['columns']) : (array) ($config['columns'] ?? []);
        $wanted = array_values(array_filter(array_map(fn (mixed $column): string => trim((string) $column), $wanted), fn (string $column): bool => $column !== ''));

        if ($wanted === []) {
            throw new InvalidArgumentException('Name at least one column.');
        }

        $range = $this->usedRange($run, $config);
        $values = array_values((array) ($range['values'] ?? []));
        $header = $this->headerOf($range);
        [$firstColumn] = self::bounds((string) ($range['address'] ?? 'A1'));
        $columns = [];

        foreach ($wanted as $column) {
            $index = array_search($column, $header, true);
            $index = $index === false && preg_match('/^[A-Za-z]{1,3}$/', $column) === 1 ? self::columnNumber($column) - $firstColumn : $index;

            if ($index === false || $index < 0 || $index >= count($header)) {
                throw new InvalidArgumentException("There is no column named {$column}.");
            }

            $columns[$column] = array_map(fn (mixed $row): mixed => array_values((array) $row)[$index] ?? null, array_slice($values, 1));
        }

        return ['columns' => $columns];
    }
}
