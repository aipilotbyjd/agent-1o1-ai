<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUnmuteMonitorNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_unmute_monitor';
    }

    public function name(): string
    {
        return 'Datadog: Unmute Monitor';
    }

    public function description(): string
    {
        return 'Unmutes a monitor so it notifies again.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
                'scope' => Field::text('Scope', 'Only this group of a multi-alert monitor.', 'host:app1'),
                'all_scopes' => Field::advanced(Field::boolean('Unmute every scope')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'monitor_id', 'The monitor');

        return $this->shapeMonitor($this->datadog($run, $config, 'POST', "/api/v1/monitor/{$id}/unmute", ['scope' => $this->text($config, 'scope'), 'all_scopes' => ($config['all_scopes'] ?? false) ? 'true' : null]));
    }
}
