<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetSecuritySignalNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_security_signal';
    }

    public function name(): string
    {
        return 'Datadog: Get Security Signal';
    }

    public function description(): string
    {
        return 'Gets a security signal.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['signal_id'],
            'properties' => [
                ...$this->credentialFields(),
                'signal_id' => Field::text('Signal id', 'From Search Security Signals.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $signal = $this->find($run, $config, '/api/v2/security_monitoring/signals/'.$this->pathId($config, 'signal_id', 'The signal id'));

        return $signal === null ? ['found' => false] : ['found' => true, ...$this->shapeSignal((array) ($signal['data'] ?? []))];
    }
}
