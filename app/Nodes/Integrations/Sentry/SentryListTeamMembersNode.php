<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryListTeamMembersNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_team_members';
    }

    public function name(): string
    {
        return 'Sentry: List Team Members';
    }

    public function description(): string
    {
        return 'Lists who is in a team.';
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
        return $this->listOf($run, $config, '/api/0/teams/'.$this->org($run, $config).'/'.$this->team($config).'/members/', 'members', [], $this->shapeMember(...));
    }
}
