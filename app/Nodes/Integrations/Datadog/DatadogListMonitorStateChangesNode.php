<?php

namespace App\Nodes\Integrations\Datadog;

use App\Nodes\Support\Field;

class DatadogListMonitorStateChangesNode extends AbstractDatadogMonitorChangeNode
{
    public function type(): string
    {
        return 'datadog_list_monitor_state_changes';
    }

    public function name(): string
    {
        return 'Datadog: List Monitor States';
    }

    public function description(): string
    {
        return 'Lists monitors with their current state. As a trigger it starts the flow whenever a monitor changes state. Checked every few minutes, or at once with Instant delivery.';
    }

    protected function extraFields(): array
    {
        return ['states' => Field::list('Only these new states', 'Alert, Warn, No Data, OK. Empty is every state.')];
    }

    protected function statesToKeep(array $config): ?array
    {
        return $this->listOf($config, 'states') ?: null;
    }

    public function triggerName(): string
    {
        return 'Datadog: Monitor state changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Datadog monitor changes state, such as OK to Alert and back. Checked every few minutes, or at once with Instant delivery.';
    }
}
