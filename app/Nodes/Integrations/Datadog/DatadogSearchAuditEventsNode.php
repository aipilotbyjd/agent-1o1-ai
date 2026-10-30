<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DatadogSearchAuditEventsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_search_audit_events';
    }

    public function name(): string
    {
        return 'Datadog: Search Audit Logs';
    }

    public function description(): string
    {
        return 'Searches the organization\'s Audit Trail.';
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
                ...$this->searchFields('Datadog audit search, like @evt.name:Monitor.', 'now-1d'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->datadog($run, $config, 'POST', '/api/v2/audit/events/search', [], $this->searchBody($config, [], 'now-1d'));
        $events = $this->records($result);

        return ['events' => $events, 'count' => count($events), 'next_cursor' => data_get($result, 'meta.page.after')];
    }
}
