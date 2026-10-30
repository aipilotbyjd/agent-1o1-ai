<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryCreateDashboardNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_create_dashboard';
    }

    public function name(): string
    {
        return 'Sentry: Create Dashboard';
    }

    public function description(): string
    {
        return 'Creates a custom dashboard.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'title' => Field::text('Title'),
                'widgets' => Field::json('Widgets', 'As in the Sentry API.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->sentry($run, $config, 'POST', '/api/0/organizations/'.$this->org($run, $config).'/dashboards/', [], ['title' => $this->requiredString($config, 'title', 'The title'), 'widgets' => $this->jsonOf($config, 'widgets', 'Widgets') ?? []]);
    }
}
