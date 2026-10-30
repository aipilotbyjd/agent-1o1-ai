<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryCreateMonitorNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_create_monitor';
    }

    public function name(): string
    {
        return 'Sentry: Create Monitor';
    }

    public function description(): string
    {
        return 'Creates a monitor in a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'name', 'type'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
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
        $this->requiredString($config, 'type', 'The type');

        return $this->sentry($run, $config, 'POST', '/api/0/organizations/'.$this->org($run, $config).'/projects/'.$this->project($config).'/detectors/', [], $body);
    }
}
