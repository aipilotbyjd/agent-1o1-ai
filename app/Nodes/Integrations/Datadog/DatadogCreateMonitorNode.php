<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateMonitorNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_monitor';
    }

    public function name(): string
    {
        return 'Datadog: Create Monitor';
    }

    public function description(): string
    {
        return 'Creates a monitor from a type, a query and a notification message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'type', 'query'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'type' => Field::select('Type', ['metric alert' => 'Metric', 'query alert' => 'Query', 'log alert' => 'Log', 'service check' => 'Service check', 'event-v2 alert' => 'Event', 'process alert' => 'Process', 'composite' => 'Composite', 'trace-analytics alert' => 'Trace analytics', 'rum alert' => 'RUM', 'slo alert' => 'SLO', 'audit alert' => 'Audit', 'ci-pipelines alert' => 'CI pipelines', 'synthetics alert' => 'Synthetics'], null, 'metric alert'),
                'query' => Field::textarea('Query', 'The alert query.', 'avg(last_5m):avg:system.cpu.user{*} > 90'),
                'message' => Field::textarea('Message', 'The notification text. Mention who to tell, like @user@example.com or @slack-channel.'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'priority' => Field::advanced(Field::integer('Priority', '1 (highest) to 5.', null, 1, 5)),
                'options' => Field::advanced(Field::json('Options', 'Thresholds, notify_no_data and so on, as in the Datadog API.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [
            'name' => $this->requiredString($config, 'name', 'The name'),
            'type' => $this->requiredString($config, 'type', 'The type'),
            'query' => $this->requiredString($config, 'query', 'The query'),
            'message' => $this->text($config, 'message') ?? '',
        ];

        if ($this->listOf($config, 'tags') !== []) {
            $body['tags'] = $this->listOf($config, 'tags');
        }

        if (filled($config['priority'] ?? null)) {
            $body['priority'] = (int) $config['priority'];
        }

        if (($options = $this->jsonOf($config, 'options', 'Options')) !== null) {
            $body['options'] = $options;
        }

        return $this->shapeMonitor($this->datadog($run, $config, 'POST', '/api/v1/monitor', [], $body));
    }
}
