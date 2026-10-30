<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogSearchSyntheticsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_synthetics';
    }

    public function name(): string
    {
        return 'Datadog: Search Synthetic Tests';
    }

    public function description(): string
    {
        return 'Searches Synthetic tests by text.';
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
                'text' => Field::text('Search', null, 'status:live'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
                'include_full_config' => Field::advanced(Field::boolean('Include the full definition')),
                'start' => Field::advanced(Field::integer('Skip', 'How many to skip.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tests = array_values(array_filter((array) ($this->datadog($run, $config, 'GET', '/api/v1/synthetics/tests/search', ['text' => $this->text($config, 'text'), 'count' => $this->limit($config), 'start' => (int) ($config['start'] ?? 0), 'include_full_config' => ($config['include_full_config'] ?? false) ? 'true' : null])['tests'] ?? []), 'is_array'));

        return ['tests' => $tests, 'count' => count($tests)];
    }
}
