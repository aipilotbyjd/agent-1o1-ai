<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryCreateTeamNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_create_team';
    }

    public function name(): string
    {
        return 'Sentry: Create Team';
    }

    public function description(): string
    {
        return 'Creates a team in an organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'name' => Field::text('Name'),
                'slug' => Field::text('Slug', 'Made from the name when empty.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->filledOf($config, ['name' => 'name', 'slug' => 'slug']);

        if ($body === []) {
            throw new InvalidArgumentException('The name or the slug is required.');
        }

        return $this->shapeTeam($this->sentry($run, $config, 'POST', '/api/0/organizations/'.$this->org($run, $config).'/teams/', [], $body));
    }
}
