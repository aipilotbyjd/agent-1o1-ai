<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListIssueEventsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_issue_events';
    }

    public function name(): string
    {
        return 'Sentry: List Issue Events';
    }

    public function description(): string
    {
        return 'Lists the events of an issue.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'issue_id' => Field::text('Issue', 'Its numeric id or short id, like BACKEND-1A.', 'BACKEND-1A'),
                'query' => Field::text('Search', 'Sentry search syntax.', 'is:unresolved level:error'),
                'environment' => Field::text('Environment'),
                'stats_period' => Field::advanced(Field::text('Period', 'Like 24h or 14d.')),
                'full' => Field::advanced(Field::boolean('Include the full event')),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/issues/'.$this->issueId($config).'/events/', 'events', ['query' => $this->text($config, 'query'), 'environment' => $this->text($config, 'environment'), 'statsPeriod' => $this->text($config, 'stats_period'), 'full' => ($config['full'] ?? false) ? 'true' : null, 'per_page' => $this->limit($config)], $this->shapeEvent(...));
    }
}
