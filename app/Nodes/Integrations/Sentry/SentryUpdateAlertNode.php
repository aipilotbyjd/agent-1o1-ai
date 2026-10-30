<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryUpdateAlertNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_alert';
    }

    public function name(): string
    {
        return 'Sentry: Update Alert';
    }

    public function description(): string
    {
        return 'Replaces an alert\'s definition.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['alert_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'alert_id' => Field::text('Alert id', 'From List Alerts.'),
                'name' => Field::text('Name'),
                'enabled' => Field::boolean('Enabled', null, true),
                'environment' => Field::text('Environment'),
                'triggers' => Field::json('When', 'The trigger group, as in the Sentry API.'),
                'action_filters' => Field::json('Do', 'The conditions and actions, as in the Sentry API.', 'array'),
                'detector_ids' => Field::advanced(Field::list('Monitor ids', 'Monitors that fire this alert.')),
                'config' => Field::advanced(Field::json('Settings', 'Like {"frequency":30}.')),
                'owner' => Field::advanced(Field::text('Owner', 'user:<id> or team:<id>.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [...$this->filledOf($config, ['name' => 'name', 'environment' => 'environment', 'owner' => 'owner']), 'enabled' => (bool) ($config['enabled'] ?? true)];

        foreach (['triggers' => 'When', 'action_filters' => 'Do', 'config' => 'Settings'] as $key => $label) {
            if (($value = $this->jsonOf($config, $key, $label)) !== null) {
                $body[$key] = $value;
            }
        }

        if ($this->items($config, 'detector_ids') !== []) {
            $body['detector_ids'] = array_map('intval', $this->items($config, 'detector_ids'));
        }

        $this->requiredString($config, 'name', 'The name');

        return $this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/workflows/'.$this->pathId($config, 'alert_id', 'The alert id').'/', [], $body);
    }
}
