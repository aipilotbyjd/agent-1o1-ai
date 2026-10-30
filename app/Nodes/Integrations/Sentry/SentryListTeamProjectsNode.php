<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryListTeamProjectsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_team_projects';
    }

    public function name(): string
    {
        return 'Sentry: List Team Projects';
    }

    public function description(): string
    {
        return 'Lists the projects of a team.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['team'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'team' => $this->teamField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/teams/'.$this->org($run, $config).'/'.$this->team($config).'/projects/', 'projects', [], $this->shapeProject(...));
    }
}
