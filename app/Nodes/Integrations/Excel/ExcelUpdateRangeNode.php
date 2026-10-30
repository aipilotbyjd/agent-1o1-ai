<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelUpdateRangeNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_update_range';
    }

    public function name(): string
    {
        return 'Excel: Update Range';
    }

    public function description(): string
    {
        return 'Writes values, formulas or number formats to a range. The rows must match the size of the range.';
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
                'values' => Field::json('Values', 'A list of rows of cell values. null leaves a cell as it is; an empty string clears it.', 'array'),
                'formulas' => Field::advanced(Field::json('Formulas', 'A list of rows of formulas, such as =SUM(A1:A9).', 'array')),
                'number_format' => Field::advanced(Field::json('Number formats', 'A list of rows of format codes, such as 0.00.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [];

        foreach (['values' => 'values', 'formulas' => 'formulas', 'number_format' => 'numberFormat'] as $key => $property) {
            if (filled($config[$key] ?? null)) {
                $body[$property] = $this->matrix($config, $key, ucfirst(str_replace('_', ' ', $key)));
            }
        }

        if ($body === []) {
            throw new InvalidArgumentException('Give values, formulas or number formats.');
        }

        return $this->shapeRange($this->graph($run, $config, 'PATCH', $this->range($config), [], $body));
    }
}
