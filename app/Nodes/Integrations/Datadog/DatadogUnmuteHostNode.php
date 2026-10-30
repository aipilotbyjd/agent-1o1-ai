<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUnmuteHostNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_unmute_host';
    }

    public function name(): string
    {
        return 'Datadog: Unmute Host';
    }

    public function description(): string
    {
        return 'Unmutes a host so it alerts again.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['host'],
            'properties' => [
                ...$this->credentialFields(),
                'host' => Field::text('Host', null, 'web-1'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $host = $this->pathId($config, 'host', 'The host');

        return $this->datadog($run, $config, 'POST', "/api/v1/host/{$host}/unmute");
    }
}
