<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryRemoveTeamFromProjectNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_remove_team_from_project';
    }

    public function name(): string
    {
        return 'Sentry: Remove Team From Project';
    }

    public function description(): string
    {
        return 'Takes a team\'s access to a project away.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $project = $this->project($config);
        $team = $this->team($config);
        $this->sentry($run, $config, 'DELETE', '/api/0/projects/'.$this->org($run, $config)."/{$project}/teams/{$team}/");

        return ['removed' => true, 'project' => $project, 'team' => $team];
    }
}
