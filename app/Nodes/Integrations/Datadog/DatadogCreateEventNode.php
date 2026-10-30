<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateEventNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_event';
    }

    public function name(): string
    {
        return 'Datadog: Create Event';
    }

    public function description(): string
    {
        return 'Posts an alert or change event to Datadog, to correlate with your monitoring.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title'),
                'message' => Field::textarea('Message'),
                'category' => Field::select('Kind', ['alert' => 'Alert', 'change' => 'Change'], null, 'alert'),
                'status' => Field::select('Alert status', ['ok' => 'OK', 'warn' => 'Warning', 'error' => 'Error'], 'For an alert.', 'ok'),
                'priority' => Field::advanced(Field::select('Alert priority', ['1' => '1 (highest)', '2' => '2', '3' => '3', '4' => '4', '5' => '5'], 'For an alert.')),
                'changed_resource' => Field::text('Changed resource', 'For a change: the name of what changed.'),
                'changed_resource_type' => Field::advanced(Field::select('Changed resource type', ['feature_flag' => 'Feature flag', 'configuration' => 'Configuration'], 'For a change.', 'configuration')),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'host' => Field::advanced(Field::text('Host')),
                'aggregation_key' => Field::advanced(Field::text('Aggregation key')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $category = $this->text($config, 'category') ?? 'alert';
        $detail = $category === 'change'
            ? ['changed_resource' => ['name' => $this->requiredString($config, 'changed_resource', 'The changed resource'), 'type' => $this->text($config, 'changed_resource_type') ?? 'configuration']]
            : array_filter(['status' => $this->text($config, 'status') ?? 'ok', 'priority' => $this->text($config, 'priority')], fn (mixed $value): bool => $value !== null);
        $attributes = array_filter([
            'title' => $this->requiredString($config, 'title', 'The title'),
            'category' => $category,
            'message' => $this->text($config, 'message'),
            'host' => $this->text($config, 'host'),
            'aggregation_key' => $this->text($config, 'aggregation_key'),
            'tags' => $this->listOf($config, 'tags') ?: null,
            'attributes' => $detail,
        ], fn (mixed $value): bool => $value !== null);
        $result = $this->datadog($run, $config, 'POST', '/api/v2/events', [], ['data' => ['type' => 'event', 'attributes' => $attributes]], 'event-management-intake');

        return ['id' => data_get($result, 'data.id'), 'accepted' => true, 'url' => data_get($result, 'links.self')];
    }
}
