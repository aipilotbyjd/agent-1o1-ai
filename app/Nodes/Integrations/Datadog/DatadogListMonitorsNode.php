<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListMonitorsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_monitors';
    }

    public function name(): string
    {
        return 'Datadog: List Monitors';
    }

    public function description(): string
    {
        return 'Lists monitors, filtered by name or tags.';
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
                'name' => Field::text('Name contains'),
                'tags' => Field::text('Scope tags', 'Comma-separated tags the monitor scopes to, like host:web-1.'),
                'monitor_tags' => Field::text('Monitor tags', 'Comma-separated tags on the monitor, like team:payments.'),
                'group_states' => Field::advanced(Field::text('Group states', 'Comma-separated, like alert,warn, to include group details.')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
                'page' => Field::advanced(Field::integer('Page', 'Starting at 0.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $monitors = $this->datadog($run, $config, 'GET', '/api/v1/monitor', [
            'name' => $this->text($config, 'name'),
            'tags' => $this->text($config, 'tags'),
            'monitor_tags' => $this->text($config, 'monitor_tags'),
            'group_states' => $this->text($config, 'group_states'),
            'page' => (int) ($config['page'] ?? 0),
            'page_size' => $this->limit($config),
        ]);
        $monitors = array_values(array_filter($monitors, 'is_array'));

        return ['monitors' => array_map($this->shapeMonitor(...), $monitors), 'count' => count($monitors)];
    }
}
