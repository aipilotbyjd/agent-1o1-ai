<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryAddTeamToProjectNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_add_team_to_project';
    }

    public function name(): string
    {
        return 'Sentry: Add Team To Project';
    }

    public function description(): string
    {
        return 'Gives a team access to a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'team'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
                'team' => $this->teamField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeProject($this->sentry($run, $config, 'POST', '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/teams/'.$this->team($config).'/'));
    }
}
