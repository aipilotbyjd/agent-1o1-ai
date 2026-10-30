<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryDeleteAlertNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_alert';
    }

    public function name(): string
    {
        return 'Sentry: Delete Alert';
    }

    public function description(): string
    {
        return 'Deletes an alert.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['alert_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'alert_id' => Field::text('Alert id', 'From List Alerts.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'alert_id', 'The alert id');
        $this->sentry($run, $config, 'DELETE', '/api/0/organizations/'.$this->org($run, $config)."/workflows/{$id}/");

        return ['deleted' => true, 'id' => $id];
    }
}
