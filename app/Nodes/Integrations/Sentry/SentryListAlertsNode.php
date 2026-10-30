<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListAlertsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_alerts';
    }

    public function name(): string
    {
        return 'Sentry: List Alerts';
    }

    public function description(): string
    {
        return 'Lists the alerts of an organization.';
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
                'project_ids' => Field::advanced(Field::list('Project ids', 'Numeric ids.')),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/workflows/', 'alerts', ['query' => $this->text($config, 'query'), 'project' => $this->items($config, 'project_ids'), 'per_page' => $this->limit($config)]);
    }
}
