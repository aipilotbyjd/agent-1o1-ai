<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryCreateProjectNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_create_project';
    }

    public function name(): string
    {
        return 'Sentry: Create Project';
    }

    public function description(): string
    {
        return 'Creates a project under a team, or under the organization when no team is chosen.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'team' => $this->teamField(),
                'name' => Field::text('Name'),
                'platform' => Field::text('Platform', null, 'python-django'),
                'slug' => Field::advanced(Field::text('Slug')),
                'default_rules' => Field::advanced(Field::boolean('Create the default alert rule', null, true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $org = $this->org($run, $config);
        $path = filled($config['team'] ?? null) ? "/api/0/teams/{$org}/".$this->team($config).'/projects/' : "/api/0/organizations/{$org}/projects/";
        $body = ['name' => $this->requiredString($config, 'name', 'The name'), ...$this->filledOf($config, ['slug' => 'slug', 'platform' => 'platform'])];

        if (($config['default_rules'] ?? true) === false) {
            $body['default_rules'] = false;
        }

        return $this->shapeProject($this->sentry($run, $config, 'POST', $path, [], $body));
    }
}
