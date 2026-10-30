<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListServicesNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_services';
    }

    public function name(): string
    {
        return 'Datadog: List Services';
    }

    public function description(): string
    {
        return 'Lists the services of the Service Catalog.';
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
        $services = $this->records($this->datadog($run, $config, 'GET', '/api/v2/services/definitions', ['page[size]' => $this->limit($config, 100), 'page[number]' => (int) ($config['page'] ?? 0)]));

        return ['services' => $services, 'count' => count($services)];
    }
}
