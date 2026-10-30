<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryDeleteProjectNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_project';
    }

    public function name(): string
    {
        return 'Sentry: Delete Project';
    }

    public function description(): string
    {
        return 'Schedules a project and all its data for deletion.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $project = $this->project($config);
        $this->sentry($run, $config, 'DELETE', '/api/0/projects/'.$this->org($run, $config)."/{$project}/");

        return ['deleted' => true, 'project' => $project];
    }
}
