<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListHostsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_hosts';
    }

    public function name(): string
    {
        return 'Datadog: List Hosts';
    }

    public function description(): string
    {
        return 'Lists infrastructure hosts, filtered by name, alias or tag.';
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
                'filter' => Field::text('Filter', 'Text in the name, an alias or a tag.', 'env:prod'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
                'from' => Field::text('Seen since', 'A date, Unix seconds, or relative like now-1d.'),
                'sort_field' => Field::advanced(Field::text('Sort by', 'Like cpu, iowait, load or apps.')),
                'sort_dir' => Field::advanced(Field::select('Direction', ['asc' => 'Ascending', 'desc' => 'Descending'])),
                'start' => Field::advanced(Field::integer('Skip', 'How many to skip.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v1/hosts', [
            'filter' => $this->text($config, 'filter'),
            'sort_field' => $this->text($config, 'sort_field'),
            'sort_dir' => $this->text($config, 'sort_dir'),
            'start' => (int) ($config['start'] ?? 0),
            'count' => $this->limit($config, 100),
            'from' => filled($config['from'] ?? null) ? $this->unixTime($config['from'], 'Seen since') : null,
        ]);
        $hosts = array_values(array_filter((array) ($result['host_list'] ?? []), 'is_array'));

        return ['hosts' => $hosts, 'count' => count($hosts), 'total' => $result['total_matching'] ?? null];
    }
}
