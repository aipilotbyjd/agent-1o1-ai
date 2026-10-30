<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * Sentry only offers these on projects with its service hooks feature; it answers 403 otherwise.
 */
class SentryCreateServiceHookNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_create_service_hook';
    }

    public function name(): string
    {
        return 'Sentry: Create Service Hook';
    }

    public function description(): string
    {
        return 'Registers a URL to be told of a project\'s new events and alerts.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'url', 'events'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
                'url' => Field::text('URL', null, 'https://example.com/hook'),
                'events' => Field::list('Events', 'event.alert and event.created.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->sentry($run, $config, 'POST', '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/hooks/', [], ['url' => $this->requiredString($config, 'url', 'The URL'), 'events' => $this->items($config, 'events') ?: throw new InvalidArgumentException('At least one event is required.')]);
    }
}
