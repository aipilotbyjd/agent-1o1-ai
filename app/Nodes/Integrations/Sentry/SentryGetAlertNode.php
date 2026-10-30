<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetAlertNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_alert';
    }

    public function name(): string
    {
        return 'Sentry: Get Alert';
    }

    public function description(): string
    {
        return 'Gets an alert.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $alert = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/workflows/'.$this->pathId($config, 'alert_id', 'The alert id').'/');

        return $alert === null ? ['found' => false] : ['found' => true, ...$alert];
    }
}
