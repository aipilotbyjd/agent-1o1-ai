<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelClearColumnNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_clear_column';
    }

    public function name(): string
    {
        return 'Excel: Clear Column';
    }

    public function description(): string
    {
        return 'Empties the cells of a column by its letter or its number.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'column'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'column' => Field::text('Column', 'A letter such as C, or a number from 1.', 'C'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $column = $this->requiredString($config, 'column', 'The column');
        $letters = ctype_digit($column) ? self::columnLetters(max(1, (int) $column)) : strtoupper($column);

        if (preg_match('/^[A-Z]{1,3}$/', $letters) !== 1) {
            throw new InvalidArgumentException('The column must be a letter such as C, or a number from 1.');
        }

        $this->graph($run, $config, 'POST', $this->range($config, "{$letters}:{$letters}").'/clear', [], ['applyTo' => 'Contents']);

        return ['cleared' => true, 'column' => $letters];
    }
}
