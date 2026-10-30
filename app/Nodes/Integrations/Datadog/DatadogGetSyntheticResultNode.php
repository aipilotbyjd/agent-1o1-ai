<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetSyntheticResultNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_synthetic_result';
    }

    public function name(): string
    {
        return 'Datadog: Get Synthetic Test Result';
    }

    public function description(): string
    {
        return 'Gets one full result of a Synthetic test.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['public_id', 'result_id'],
            'properties' => [
                ...$this->credentialFields(),
                'public_id' => $this->syntheticField(),
                'result_id' => Field::text('Result id'),
                'test_type' => Field::select('Test kind', ['api' => 'API', 'browser' => 'Browser'], null, 'api'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'public_id', 'The test');
        $result = $this->pathId($config, 'result_id', 'The result id');
        $path = ($config['test_type'] ?? 'api') === 'browser' ? "/api/v1/synthetics/tests/browser/{$id}/results/{$result}" : "/api/v1/synthetics/tests/{$id}/results/{$result}";
        $found = $this->find($run, $config, $path);

        return $found === null ? ['found' => false] : ['found' => true, ...$found];
    }
}
