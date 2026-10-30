<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelCallFunctionNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_call_function';
    }

    public function name(): string
    {
        return 'Excel: Call Function';
    }

    public function description(): string
    {
        return 'Runs an Excel worksheet function, such as PMT or VLOOKUP, and returns the result.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'function', 'arguments'],
            'properties' => [
                ...$this->workbookFields(),
                'function' => Field::text('Function', 'The function\'s name.', 'pmt'),
                'arguments' => Field::json('Arguments', 'An object of its arguments, as Graph names them, such as {"rate": 0.045, "nper": 12, "pv": -1250}.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $function = $this->requiredString($config, 'function', 'The function');

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.]{0,63}$/', $function) !== 1) {
            throw new InvalidArgumentException('The function must be a name such as pmt.');
        }

        $result = $this->graph($run, $config, 'POST', $this->workbook($config).'/functions/'.strtolower($function), [], $this->decoded($config, 'arguments', 'Arguments'));

        return ['value' => $result['value'] ?? null, 'error' => $result['error'] ?? null];
    }
}
