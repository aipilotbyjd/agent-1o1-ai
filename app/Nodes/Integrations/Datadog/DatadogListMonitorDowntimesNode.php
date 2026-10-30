<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListMonitorDowntimesNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_monitor_downtimes';
    }

    public function name(): string
    {
        return 'Datadog: List Monitor Downtimes';
    }

    public function description(): string
    {
        return 'Lists the downtimes active on a monitor.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
                'limit' => Field::integer('Max results', null, 30, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'monitor_id', 'The monitor');
        $downtimes = $this->records($this->datadog($run, $config, 'GET', "/api/v2/monitor/{$id}/downtime_matches", ['page[limit]' => $this->limit($config, 30, 100)]));

        return ['downtimes' => $downtimes, 'count' => count($downtimes)];
    }
}
