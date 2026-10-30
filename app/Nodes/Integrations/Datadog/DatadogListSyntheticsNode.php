<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListSyntheticsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_synthetics';
    }

    public function name(): string
    {
        return 'Datadog: List Synthetic Tests';
    }

    public function description(): string
    {
        return 'Lists Synthetic tests.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
                'page' => Field::advanced(Field::integer('Page', 'Starting at 0.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tests = array_values(array_filter((array) ($this->datadog($run, $config, 'GET', '/api/v1/synthetics/tests', ['page_size' => $this->limit($config, 100), 'page_number' => (int) ($config['page'] ?? 0)])['tests'] ?? []), 'is_array'));

        return ['tests' => $tests, 'count' => count($tests)];
    }
}
