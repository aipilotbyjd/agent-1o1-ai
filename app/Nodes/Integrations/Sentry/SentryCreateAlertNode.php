<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryCreateAlertNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_create_alert';
    }

    public function name(): string
    {
        return 'Sentry: Create Alert';
    }

    public function description(): string
    {
        return 'Creates an alert in an organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
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

        return $this->sentry($run, $config, 'POST', '/api/0/organizations/'.$this->org($run, $config).'/workflows/', [], $body);
    }
}
