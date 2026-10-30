<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogListSyntheticLocationsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_synthetic_locations';
    }

    public function name(): string
    {
        return 'Datadog: List Synthetic Locations';
    }

    public function description(): string
    {
        return 'Lists the public and private locations tests can run from.';
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

            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $locations = array_values((array) ($this->datadog($run, $config, 'GET', '/api/v1/synthetics/locations')['locations'] ?? []));

        return ['locations' => $locations, 'count' => count($locations)];
    }
}
