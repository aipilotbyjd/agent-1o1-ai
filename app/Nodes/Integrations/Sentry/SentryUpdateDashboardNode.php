<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryUpdateDashboardNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_dashboard';
    }

    public function name(): string
    {
        return 'Sentry: Update Dashboard';
    }

    public function description(): string
    {
        return 'Changes a dashboard\'s title or widgets.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['dashboard_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'dashboard_id' => Field::text('Dashboard id', 'From List Dashboards.'),
                'title' => Field::text('Title'),
                'widgets' => Field::json('Widgets', 'Replaces every widget.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->filledOf($config, ['title' => 'title']);

        if (($widgets = $this->jsonOf($config, 'widgets', 'Widgets')) !== null) {
            $body['widgets'] = $widgets;
        }

        if ($body === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/dashboards/'.$this->pathId($config, 'dashboard_id', 'The dashboard id').'/', [], $body);
    }
}
