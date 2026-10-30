<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogGetCurrentUserNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_current_user';
    }

    public function name(): string
    {
        return 'Datadog: Get Current User';
    }

    public function description(): string
    {
        return 'Gets the user the application key belongs to.';
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
        return $this->flatten((array) ($this->datadog($run, $config, 'GET', '/api/v2/current_user')['data'] ?? []));
    }
}
