<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetDowntimeNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_downtime';
    }

    public function name(): string
    {
        return 'Datadog: Get Downtime';
    }

    public function description(): string
    {
        return 'Gets a scheduled downtime.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['downtime_id'],
            'properties' => [
                ...$this->credentialFields(),
                'downtime_id' => Field::text('Downtime id', 'From List Downtimes.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $downtime = $this->find($run, $config, '/api/v2/downtime/'.$this->pathId($config, 'downtime_id', 'The downtime id'));

        return $downtime === null ? ['found' => false] : ['found' => true, ...$this->flatten((array) ($downtime['data'] ?? []))];
    }
}
