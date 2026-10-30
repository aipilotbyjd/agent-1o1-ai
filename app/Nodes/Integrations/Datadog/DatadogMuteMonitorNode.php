<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogMuteMonitorNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_mute_monitor';
    }

    public function name(): string
    {
        return 'Datadog: Mute Monitor';
    }

    public function description(): string
    {
        return 'Mutes a monitor so it stops notifying, for good or until a set time.';
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
                'end' => Field::datetime('Until', 'Empty mutes it until it is unmuted.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'monitor_id', 'The monitor');
        $end = filled($config['end'] ?? null) ? $this->unixTime($config['end'], 'Until') : null;

        return $this->shapeMonitor($this->datadog($run, $config, 'POST', "/api/v1/monitor/{$id}/mute", ['scope' => $this->text($config, 'scope'), 'end' => $end]));
    }
}
