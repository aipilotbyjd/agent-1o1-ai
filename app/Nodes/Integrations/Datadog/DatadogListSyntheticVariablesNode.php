<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogListSyntheticVariablesNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_synthetic_variables';
    }

    public function name(): string
    {
        return 'Datadog: List Synthetic Variables';
    }

    public function description(): string
    {
        return 'Lists the global variables of Synthetic tests.';
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
        $variables = array_values((array) ($this->datadog($run, $config, 'GET', '/api/v1/synthetics/variables')['variables'] ?? []));

        return ['variables' => $variables, 'count' => count($variables)];
    }
}
