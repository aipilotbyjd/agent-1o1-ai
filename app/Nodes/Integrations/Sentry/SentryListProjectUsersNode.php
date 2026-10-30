<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListProjectUsersNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_project_users';
    }

    public function name(): string
    {
        return 'Sentry: List Project Users';
    }

    public function description(): string
    {
        return 'Lists the users seen by a project.';
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
                'query' => Field::text('Search', 'Like email:name@example.com.'),
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/users/', 'users', ['query' => $this->text($config, 'query')]);
    }
}
