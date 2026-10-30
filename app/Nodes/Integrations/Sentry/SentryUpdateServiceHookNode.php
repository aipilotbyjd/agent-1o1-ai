<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * Sentry only offers these on projects with its service hooks feature; it answers 403 otherwise.
 */
class SentryUpdateServiceHookNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_service_hook';
    }

    public function name(): string
    {
        return 'Sentry: Update Service Hook';
    }

    public function description(): string
    {
        return 'Changes a service hook\'s URL and events.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'hook_id', 'url', 'events'],
            'properties' => [
                ...$this->credentialFields(),
                'hook_id' => Field::text('Hook id', 'From List Service Hooks.'),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
                'url' => Field::text('URL', null, 'https://example.com/hook'),
                'events' => Field::list('Events', 'event.alert and event.created.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->sentry($run, $config, 'PUT', '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/hooks/'.$this->pathId($config, 'hook_id', 'The hook id').'/', [], ['url' => $this->requiredString($config, 'url', 'The URL'), 'events' => $this->items($config, 'events') ?: throw new InvalidArgumentException('At least one event is required.')]);
    }
}
