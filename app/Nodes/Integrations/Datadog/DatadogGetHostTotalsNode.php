<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetHostTotalsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_host_totals';
    }

    public function name(): string
    {
        return 'Datadog: Get Host Totals';
    }

    public function description(): string
    {
        return 'Gets the number of active and up hosts.';
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
                'from' => Field::text('Seen since', 'A date, Unix seconds, or relative like now-1d.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->datadog($run, $config, 'GET', '/api/v1/hosts/totals', ['from' => filled($config['from'] ?? null) ? $this->unixTime($config['from'], 'Seen since') : null]);
    }
}
