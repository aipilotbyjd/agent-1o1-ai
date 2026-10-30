<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogCreateSloNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_slo';
    }

    public function name(): string
    {
        return 'Datadog: Create SLO';
    }

    public function description(): string
    {
        return 'Creates a service level objective.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'type', 'thresholds'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'type' => Field::select('Type', ['metric' => 'Metric', 'monitor' => 'Monitor', 'time_slice' => 'Time slice'], null, 'metric'),
                'thresholds' => Field::json('Thresholds', 'Like [{"target":99.9,"timeframe":"30d"}].', 'array'),
                'query' => Field::json('Metric query', 'For a metric SLO: {"numerator":"...","denominator":"..."}.'),
                'monitor_ids' => Field::list('Monitor ids', 'For a monitor SLO.'),
                'description' => Field::textarea('Description'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'groups' => Field::advanced(Field::list('Groups', 'For a monitor SLO on a multi-alert monitor.')),
                'sli_specification' => Field::advanced(Field::json('SLI specification', 'For a time slice SLO.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter([
            'name' => $this->requiredString($config, 'name', 'The name'),
            'type' => $this->requiredString($config, 'type', 'The type'),
            'thresholds' => $this->jsonOf($config, 'thresholds', 'Thresholds') ?? throw new InvalidArgumentException('Thresholds are required.'),
            'description' => $this->text($config, 'description'),
            'tags' => $this->listOf($config, 'tags') ?: null,
            'query' => $this->jsonOf($config, 'query', 'Query'),
            'monitor_ids' => array_map('intval', $this->listOf($config, 'monitor_ids')) ?: null,
            'groups' => $this->listOf($config, 'groups') ?: null,
            'sli_specification' => $this->jsonOf($config, 'sli_specification', 'SLI specification'),
        ], fn (mixed $value): bool => $value !== null);
        $created = (array) ($this->datadog($run, $config, 'POST', '/api/v1/slo', [], $body)['data'] ?? []);

        return (array) ($created[0] ?? $created);
    }
}
