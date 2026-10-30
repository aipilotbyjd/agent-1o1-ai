<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryGetProjectNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_project';
    }

    public function name(): string
    {
        return 'Sentry: Get Project';
    }

    public function description(): string
    {
        return 'Gets a project with its settings.';
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
        $project = $this->find($run, $config, '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/');

        return $project === null ? ['found' => false] : ['found' => true, ...$project];
    }
}
