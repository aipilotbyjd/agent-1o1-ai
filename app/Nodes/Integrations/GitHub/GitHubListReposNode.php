<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubListReposNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_list_repos';
    }

    public function name(): string
    {
        return 'GitHub: List Repos';
    }

    public function description(): string
    {
        return 'Lists repositories for the authenticated user or an organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'owner' => Field::dynamic('Organization', 'github.owners', 'Leave empty to list your own repositories.'),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $endpoint = isset($config['owner']) ? "/orgs/{$config['owner']}/repos" : '/user/repos';

        return ['repos' => $this->get($run, $endpoint, $config, ['per_page' => $config['per_page'] ?? 30])];
    }
}
