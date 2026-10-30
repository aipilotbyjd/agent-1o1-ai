<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogUpdateMonitorNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_monitor';
    }

    public function name(): string
    {
        return 'Datadog: Update Monitor';
    }

    public function description(): string
    {
        return 'Changes the settings filled in here on a monitor.';
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
                'name' => Field::text('Name'),
                'query' => Field::textarea('Query'),
                'message' => Field::textarea('Message'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'type' => Field::advanced(Field::text('Type', 'Only a few types can be changed; see the Datadog API.')),
                'priority' => Field::advanced(Field::integer('Priority', '1 (highest) to 5.', null, 1, 5)),
                'options' => Field::advanced(Field::json('Options')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'monitor_id', 'The monitor');
        $body = array_filter(['name' => $this->text($config, 'name'), 'query' => $this->text($config, 'query'), 'message' => $this->text($config, 'message'), 'type' => $this->text($config, 'type')], fn (mixed $value): bool => $value !== null);

        if ($this->listOf($config, 'tags') !== []) {
            $body['tags'] = $this->listOf($config, 'tags');
        }

        if (filled($config['priority'] ?? null)) {
            $body['priority'] = (int) $config['priority'];
        }

        if (($options = $this->jsonOf($config, 'options', 'Options')) !== null) {
            $body['options'] = $options;
        }

        if ($body === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeMonitor($this->datadog($run, $config, 'PUT', "/api/v1/monitor/{$id}", [], $body));
    }
}
