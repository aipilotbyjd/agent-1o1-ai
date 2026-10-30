<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

/**
 * Sentry only offers these on projects with its service hooks feature; it answers 403 otherwise.
 */
class SentryDeleteServiceHookNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_service_hook';
    }

    public function name(): string
    {
        return 'Sentry: Delete Service Hook';
    }

    public function description(): string
    {
        return 'Removes a service hook.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'hook_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
                'hook_id' => Field::text('Hook id', 'From List Service Hooks.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $hook = $this->pathId($config, 'hook_id', 'The hook id');
        $this->sentry($run, $config, 'DELETE', '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config)."/hooks/{$hook}/");

        return ['deleted' => true, 'id' => $hook];
    }
}
