<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListDowntimesNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_downtimes';
    }

    public function name(): string
    {
        return 'Datadog: List Downtimes';
    }

    public function description(): string
    {
        return 'Lists scheduled downtimes.';
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
                'current_only' => Field::boolean('Only current downtimes'),
                'limit' => Field::integer('Max results', null, 30, 1, 100),
                'offset' => Field::advanced(Field::integer('Skip', 'How many to skip.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'GET', '/api/v2/downtime', ['current_only' => ($config['current_only'] ?? false) ? 'true' : null, 'page[limit]' => $this->limit($config, 30, 100), 'page[offset]' => (int) ($config['offset'] ?? 0)]);
        $downtimes = $this->records($result);

        return ['downtimes' => $downtimes, 'count' => count($downtimes)];
    }
}
