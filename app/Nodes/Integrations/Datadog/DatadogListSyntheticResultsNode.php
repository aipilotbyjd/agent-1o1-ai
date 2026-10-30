<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListSyntheticResultsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_synthetic_results';
    }

    public function name(): string
    {
        return 'Datadog: List Synthetic Test Results';
    }

    public function description(): string
    {
        return 'Lists the latest results of a Synthetic test.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['public_id'],
            'properties' => [
                ...$this->credentialFields(),
                'public_id' => $this->syntheticField(),
                'test_type' => Field::select('Test kind', ['api' => 'API', 'browser' => 'Browser'], null, 'api'),
                'from' => Field::text('From', 'A date, Unix seconds, or relative.'),
                'to' => Field::text('To', 'Default now.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'public_id', 'The test');
        $path = ($config['test_type'] ?? 'api') === 'browser' ? "/api/v1/synthetics/tests/browser/{$id}/results" : "/api/v1/synthetics/tests/{$id}/results";
        $results = array_values((array) ($this->datadog($run, $config, 'GET', $path, ['from_ts' => filled($config['from'] ?? null) ? $this->unixTime($config['from'], 'From') * 1000 : null, 'to_ts' => filled($config['to'] ?? null) ? $this->unixTime($config['to'], 'To') * 1000 : null])['results'] ?? []));

        return ['results' => $results, 'count' => count($results)];
    }
}
