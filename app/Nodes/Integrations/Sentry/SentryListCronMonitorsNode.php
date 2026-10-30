<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListCronMonitorsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_cron_monitors';
    }

    public function name(): string
    {
        return 'Sentry: List Cron Monitors';
    }

    public function description(): string
    {
        return 'Lists the cron monitors of an organization.';
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
                'environment' => Field::text('Environment'),
                'owner' => Field::advanced(Field::text('Owner', 'user:<id> or team:<id>.')),
                'project_ids' => Field::advanced(Field::list('Project ids', 'Numeric ids.')),
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/monitors/', 'monitors', ['project' => $this->items($config, 'project_ids'), 'environment' => $this->text($config, 'environment'), 'owner' => $this->text($config, 'owner')]);
    }
}
