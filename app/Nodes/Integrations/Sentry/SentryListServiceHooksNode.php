<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

/**
 * Sentry only offers these on projects with its service hooks feature; it answers 403 otherwise.
 */
class SentryListServiceHooksNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_service_hooks';
    }

    public function name(): string
    {
        return 'Sentry: List Service Hooks';
    }

    public function description(): string
    {
        return 'Lists the service hooks of a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/hooks/', 'hooks');
    }
}
