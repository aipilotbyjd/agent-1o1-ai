<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogGetSloNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_slo';
    }

    public function name(): string
    {
        return 'Datadog: Get SLO';
    }

    public function description(): string
    {
        return 'Gets a service level objective.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['slo_id'],
            'properties' => [
                ...$this->credentialFields(),
                'slo_id' => $this->sloField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $slo = $this->find($run, $config, '/api/v1/slo/'.$this->pathId($config, 'slo_id', 'The SLO'));

        return $slo === null ? ['found' => false] : ['found' => true, ...((array) ($slo['data'] ?? []))];
    }
}
