<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryUpdateTeamNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_team';
    }

    public function name(): string
    {
        return 'Sentry: Update Team';
    }

    public function description(): string
    {
        return 'Changes a team\'s name or slug.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'name' => Field::text('Name'),
                'slug' => Field::text('New slug'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $team = $this->team($config);

        return $this->shapeTeam($this->sentry($run, $config, 'PUT', '/api/0/teams/'.$this->org($run, $config)."/{$team}/", [], ['slug' => $this->text($config, 'slug') ?? $team, ...$this->filledOf($config, ['name' => 'name'])]));
    }
}
