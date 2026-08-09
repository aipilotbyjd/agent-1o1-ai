<?php

namespace App\Nodes\Integrations\GitHub;

use App\Models\Runs\Run;

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

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['access_token'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'owner' => ['type' => 'string'],
                'per_page' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $endpoint = isset($config['owner']) ? "/orgs/{$config['owner']}/repos" : '/user/repos';

        return ['repos' => $this->get($endpoint, $config, ['per_page' => $config['per_page'] ?? 30])];
    }
}
