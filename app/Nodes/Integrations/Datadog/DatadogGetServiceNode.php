<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetServiceNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_service';
    }

    public function name(): string
    {
        return 'Datadog: Get Service';
    }

    public function description(): string
    {
        return 'Gets a service definition from the Service Catalog.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['service'],
            'properties' => [
                ...$this->credentialFields(),
                'service' => Field::text('Service', null, 'web'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $service = $this->find($run, $config, '/api/v2/services/definitions/'.$this->pathId($config, 'service', 'The service'));

        return $service === null ? ['found' => false] : ['found' => true, ...$this->flatten((array) ($service['data'] ?? []))];
    }
}
