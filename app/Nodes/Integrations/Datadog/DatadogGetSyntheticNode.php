<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogGetSyntheticNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_synthetic';
    }

    public function name(): string
    {
        return 'Datadog: Get Synthetic Test';
    }

    public function description(): string
    {
        return 'Gets a Synthetic test.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $test = $this->find($run, $config, '/api/v1/synthetics/tests/'.$this->pathId($config, 'public_id', 'The test'));

        return $test === null ? ['found' => false] : ['found' => true, ...$test];
    }
}
