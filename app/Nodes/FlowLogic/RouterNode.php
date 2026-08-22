<?php

namespace App\Nodes\FlowLogic;

use App\Contracts\NodeContract;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use Illuminate\Support\Arr;

/**
 * Evaluates `config.conditions` in order and outputs the first matching
 * branch's `result`, falling back to `'default'`. `GraphAdvancer` (Stage 3)
 * matches each outgoing `WorkflowEdge.condition` against this `result`.
 */
class RouterNode implements NodeContract
{
    private const array OPERATORS = ['equals', 'not_equals', 'contains', 'greater_than', 'less_than'];

    public function type(): string
    {
        return 'router';
    }

    public function category(): string
    {
        return NodeCategory::FlowLogic->value;
    }

    public function name(): string
    {
        return 'Router';
    }

    public function description(): string
    {
        return 'Branches into one of several named outcomes based on evaluating conditions in order.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conditions'],
            'properties' => [
                'conditions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['path', 'operator', 'value', 'result'],
                        'properties' => [
                            'path' => ['type' => 'string'],
                            'operator' => ['type' => 'string', 'enum' => self::OPERATORS],
                            'value' => [],
                            'result' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * The branches this router can actually take: every configured
     * condition's `result`, plus the `default` fall-through `execute()`
     * returns when none match. Config-derived because a router's outcomes are
     * exactly what its author named them.
     */
    public function outputSchema(array $config = []): array
    {
        $results = collect($config['conditions'] ?? [])
            ->pluck('result')
            ->filter(fn ($result): bool => is_string($result) && $result !== '')
            ->push('default')
            ->unique()
            ->values()
            ->all();

        return [
            'type' => 'object',
            'properties' => [
                'result' => ['type' => 'string', 'enum' => $results],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        foreach ($config['conditions'] ?? [] as $condition) {
            $actual = Arr::get($context, $condition['path'] ?? '');

            if ($this->matches($actual, $condition['operator'], $condition['value'])) {
                return ['result' => $condition['result']];
            }
        }

        return ['result' => 'default'];
    }

    private function matches(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            'greater_than' => is_numeric($actual) && $actual > $expected,
            'less_than' => is_numeric($actual) && $actual < $expected,
            default => false,
        };
    }
}
