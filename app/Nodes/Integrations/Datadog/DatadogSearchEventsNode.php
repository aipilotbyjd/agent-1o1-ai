<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogSearchEventsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_events';
    }

    public function name(): string
    {
        return 'Datadog: Search Events';
    }

    public function description(): string
    {
        return 'Searches events with the full search syntax.';
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
                ...$this->searchFields('Datadog event search.', 'now-1h'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'POST', '/api/v2/events/search', [], $this->searchBody($config, [], 'now-1h'));
        $events = array_values(array_filter((array) ($result['data'] ?? []), 'is_array'));

        return ['events' => array_map($this->shapeEvent(...), $events), 'count' => count($events), 'next_cursor' => data_get($result, 'meta.page.after')];
    }
}
