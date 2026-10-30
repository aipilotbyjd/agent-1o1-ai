<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogDeleteServiceNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_service';
    }

    public function name(): string
    {
        return 'Datadog: Delete Service';
    }

    public function description(): string
    {
        return 'Deletes a service definition from the Service Catalog.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $service = $this->pathId($config, 'service', 'The service');
        $this->datadog($run, $config, 'DELETE', "/api/v2/services/definitions/{$service}");

        return ['deleted' => true, 'service' => rawurldecode($service)];
    }
}
