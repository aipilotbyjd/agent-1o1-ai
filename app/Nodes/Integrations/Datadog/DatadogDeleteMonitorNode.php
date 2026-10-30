<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogDeleteMonitorNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_monitor';
    }

    public function name(): string
    {
        return 'Datadog: Delete Monitor';
    }

    public function description(): string
    {
        return 'Deletes a monitor.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
                'force' => Field::advanced(Field::boolean('Delete even if other things use it')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'monitor_id', 'The monitor');
        $this->datadog($run, $config, 'DELETE', "/api/v1/monitor/{$id}", ['force' => ($config['force'] ?? false) ? 'true' : null]);

        return ['deleted' => true, 'id' => $id];
    }
}
