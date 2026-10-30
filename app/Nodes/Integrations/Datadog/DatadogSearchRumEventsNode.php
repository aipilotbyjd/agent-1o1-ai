<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogSearchRumEventsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_rum_events';
    }

    public function name(): string
    {
        return 'Datadog: Search RUM Events';
    }

    public function description(): string
    {
        return 'Searches Real User Monitoring events by query and time range.';
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
                ...$this->searchFields('Datadog RUM search, like @type:error.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'POST', '/api/v2/rum/events/search', [], $this->searchBody($config));
        $events = $this->records($result);

        return ['events' => $events, 'count' => count($events), 'next_cursor' => data_get($result, 'meta.page.after')];
    }
}
