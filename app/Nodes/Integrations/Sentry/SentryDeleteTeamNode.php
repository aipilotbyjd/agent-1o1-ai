<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryDeleteTeamNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_team';
    }

    public function name(): string
    {
        return 'Sentry: Delete Team';
    }

    public function description(): string
    {
        return 'Schedules a team for deletion.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $team = $this->team($config);
        $this->sentry($run, $config, 'DELETE', '/api/0/teams/'.$this->org($run, $config)."/{$team}/");

        return ['deleted' => true, 'team' => $team];
    }
}
