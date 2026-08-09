<?php

namespace App\Nodes\FlowLogic;

use App\Contracts\NodeContract;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use Illuminate\Support\Arr;

/**
 * A binary gate: `result` is `'passed'`/`'failed'` depending on whether
 * `config.path` matches `config.operator`/`config.value` against `$context`.
 * `GraphAdvancer` routes the non-matching branch to `skipped` via the edge
 * whose `condition` doesn't match this node's `result`.
 */
class FilterNode implements NodeContract
{
    private const array OPERATORS = ['equals', 'not_equals', 'contains', 'greater_than', 'less_than', 'is_empty', 'is_not_empty'];

    public function type(): string
    {
        return 'filter';
    }

    public function category(): string
    {
        return NodeCategory::FlowLogic->value;
    }

    public function name(): string
    {
        return 'Filter';
    }

    public function description(): string
    {
        return 'A binary gate — passes or fails based on comparing a context value against a condition.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['path', 'operator'],
            'properties' => [
                'path' => ['type' => 'string'],
                'operator' => ['type' => 'string', 'enum' => self::OPERATORS],
                'value' => [],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $actual = Arr::get($context, $config['path'] ?? '');
        $passed = $this->matches($actual, $config['operator'], $config['value'] ?? null);

        return ['result' => $passed ? 'passed' : 'failed', 'passed' => $passed];
    }

    private function matches(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            'greater_than' => is_numeric($actual) && $actual > $expected,
            'less_than' => is_numeric($actual) && $actual < $expected,
            'is_empty' => empty($actual),
            'is_not_empty' => ! empty($actual),
        };
    }
}
