<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryListProjectTeamsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_project_teams';
    }

    public function name(): string
    {
        return 'Sentry: List Project Teams';
    }

    public function description(): string
    {
        return 'Lists the teams with access to a project.';
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
        return $this->listOf($run, $config, '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/teams/', 'teams', [], $this->shapeTeam(...));
    }
}
