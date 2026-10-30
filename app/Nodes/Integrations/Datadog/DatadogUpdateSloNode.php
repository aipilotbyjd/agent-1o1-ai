<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpdateSloNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_slo';
    }

    public function name(): string
    {
        return 'Datadog: Update SLO';
    }

    public function description(): string
    {
        return 'Changes the settings filled in here on a service level objective; the rest stays as it is.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['slo_id'],
            'properties' => [
                ...$this->credentialFields(),
                'slo_id' => $this->sloField(),
                'name' => Field::text('Name'),
                'description' => Field::textarea('Description'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'thresholds' => Field::json('Thresholds', null, 'array'),
                'query' => Field::advanced(Field::json('Metric query')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'slo_id', 'The SLO');
        $current = (array) ($this->datadog($run, $config, 'GET', "/api/v1/slo/{$id}")['data'] ?? []);
        $body = array_intersect_key($current, array_flip(['name', 'type', 'thresholds', 'description', 'tags', 'query', 'monitor_ids', 'groups', 'sli_specification']));
        $body = [...$body, ...array_filter(['name' => $this->text($config, 'name'), 'thresholds' => $this->jsonOf($config, 'thresholds', 'Thresholds'), 'description' => $this->text($config, 'description'), 'tags' => $this->listOf($config, 'tags') ?: null, 'query' => $this->jsonOf($config, 'query', 'Query')], fn (mixed $value): bool => $value !== null)];
        $updated = (array) ($this->datadog($run, $config, 'PUT', "/api/v1/slo/{$id}", [], $body)['data'] ?? []);

        return (array) ($updated[0] ?? $updated);
    }
}
