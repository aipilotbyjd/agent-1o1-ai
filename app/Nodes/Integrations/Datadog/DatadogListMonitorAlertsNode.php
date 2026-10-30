<?php

namespace App\Nodes\Integrations\Datadog;

class DatadogListMonitorAlertsNode extends AbstractDatadogMonitorChangeNode
{
    public function type(): string
    {
        return 'datadog_list_monitor_alerts';
    }

    public function name(): string
    {
        return 'Datadog: List Alerting Monitors';
    }

    public function description(): string
    {
        return 'Lists monitors in alert, warning or no data. As a trigger it starts the flow when one starts alerting. Checked every few minutes, or at once with Instant delivery.';
    }

    protected function statesToKeep(array $config): ?array
    {
        return ['Alert', 'Warn', 'No Data'];
    }

    public function triggerName(): string
    {
        return 'Datadog: Monitor alert';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Datadog monitor goes into alert, warning or no data. Checked every few minutes, or at once with Instant delivery.';
    }
}
