<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryUpdateMonitorNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_monitor';
    }

    public function name(): string
    {
        return 'Sentry: Update Monitor';
    }

    public function description(): string
    {
        return 'Replaces a monitor\'s definition.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'monitor_id' => Field::text('Monitor id', 'From List Monitors.'),
                'name' => Field::text('Name'),
                'type' => Field::text('Type', null, 'metric_issue'),
                'description' => Field::textarea('Description'),
                'enabled' => Field::boolean('Enabled', null, true),
                'data_sources' => Field::json('Data sources', 'As in the Sentry API.', 'array'),
                'condition_group' => Field::json('Conditions', 'As in the Sentry API.'),
                'config' => Field::advanced(Field::json('Settings')),
                'workflow_ids' => Field::advanced(Field::list('Alert ids', 'Alerts this monitor fires.')),
                'owner' => Field::advanced(Field::text('Owner', 'user:<id> or team:<id>.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [...$this->filledOf($config, ['name' => 'name', 'type' => 'type', 'description' => 'description', 'owner' => 'owner']), 'enabled' => (bool) ($config['enabled'] ?? true)];

        foreach (['data_sources' => 'Data sources', 'condition_group' => 'Conditions', 'config' => 'Settings'] as $key => $label) {
            if (($value = $this->jsonOf($config, $key, $label)) !== null) {
                $body[$key] = $value;
            }
        }

        if ($this->items($config, 'workflow_ids') !== []) {
            $body['workflow_ids'] = array_map('intval', $this->items($config, 'workflow_ids'));
        }

        $this->requiredString($config, 'name', 'The name');

        return $this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/detectors/'.$this->pathId($config, 'monitor_id', 'The monitor id').'/', [], $body);
    }
}
