<?php

namespace App\Nodes\FlowLogic;

use App\Contracts\NodeContract;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;

/**
 * Implements `NodeContract` (unlike its `FlowLogic` siblings) — `execute()`
 * itself does no waiting, it just reports how long to wait. The engine
 * (`WorkflowRunner`) reads `seconds` back off the output and delays the
 * *next* dispatch by that long, rather than blocking a queue worker inside
 * `execute()`.
 */
class DelayNode implements NodeContract
{
    public function type(): string
    {
        return 'delay';
    }

    public function category(): string
    {
        return NodeCategory::FlowLogic->value;
    }

    public function name(): string
    {
        return 'Delay';
    }

    public function description(): string
    {
        return 'Pauses before the next node runs, for a configured number of seconds.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['seconds'],
            'properties' => [
                'seconds' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(array $config = []): array
    {
        return [
            'type' => 'object',
            'properties' => ['seconds' => ['type' => 'integer']],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['seconds' => max(0, (int) $config['seconds'])];
    }
}
