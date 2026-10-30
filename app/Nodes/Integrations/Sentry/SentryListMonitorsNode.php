<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListMonitorsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_monitors';
    }

    public function name(): string
    {
        return 'Sentry: List Monitors';
    }

    public function description(): string
    {
        return 'Lists the monitors of an organization: metric, uptime, cron and error.';
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
                'organization' => $this->organizationField(),
                'query' => Field::text('Search'),
                'types' => Field::advanced(Field::list('Types', 'Like metric_issue or uptime_domain_failure.')),
                'project_ids' => Field::advanced(Field::list('Project ids', 'Numeric ids.')),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/detectors/', 'monitors', ['query' => $this->text($config, 'query'), 'project' => $this->items($config, 'project_ids'), 'type' => $this->items($config, 'types'), 'per_page' => $this->limit($config)]);
    }
}
