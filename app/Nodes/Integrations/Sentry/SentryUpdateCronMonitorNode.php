<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryUpdateCronMonitorNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_cron_monitor';
    }

    public function name(): string
    {
        return 'Sentry: Update Cron Monitor';
    }

    public function description(): string
    {
        return 'Changes a cron monitor.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['cron', 'name', 'cron_project', 'config'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'cron' => Field::text('Cron monitor', 'Its slug or id.', 'nightly-job'),
                'name' => Field::text('Name'),
                'cron_project' => Field::text('Project', 'Slug.'),
                'config' => Field::json('Schedule settings', 'Like {"schedule_type":"crontab","schedule":"0 2 * * *"}.'),
                'slug' => Field::advanced(Field::text('Slug')),
                'status' => Field::advanced(Field::select('Status', ['active' => 'Active', 'disabled' => 'Disabled'])),
                'owner' => Field::advanced(Field::text('Owner', 'user:<id> or team:<id>.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->filledOf($config, ['name' => 'name', 'cron_project' => 'project', 'slug' => 'slug', 'status' => 'status', 'owner' => 'owner']);

        if (($settings = $this->jsonOf($config, 'config', 'Schedule settings')) !== null) {
            $body['config'] = $settings;
        }

        foreach (['name', 'project', 'config'] as $required) {
            if (! isset($body[$required])) {
                throw new InvalidArgumentException("The {$required} setting is required.");
            }
        }

        return $this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/monitors/'.$this->pathId($config, 'cron', 'The cron monitor').'/', [], $body);
    }
}
