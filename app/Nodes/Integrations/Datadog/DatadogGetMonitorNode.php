<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetMonitorNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_monitor';
    }

    public function name(): string
    {
        return 'Datadog: Get Monitor';
    }

    public function description(): string
    {
        return 'Gets a monitor and its current state.';
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
                'group_states' => Field::advanced(Field::text('Group states', 'Comma-separated, like alert,warn. Default all.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $monitor = $this->find($run, $config, '/api/v1/monitor/'.$this->numericId($config, 'monitor_id', 'The monitor'), ['group_states' => $this->text($config, 'group_states') ?? 'all']);

        return $monitor === null ? ['found' => false] : ['found' => true, ...$this->shapeMonitor($monitor)];
    }
}
