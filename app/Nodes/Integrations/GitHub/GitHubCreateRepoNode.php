<?php

namespace App\Nodes\Integrations\GitHub;

use App\Models\Runs\Run;

class GitHubCreateRepoNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_repo';
    }

    public function name(): string
    {
        return 'GitHub: Create Repo';
    }

    public function description(): string
    {
        return 'Creates a new repository for the authenticated user or an organization.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'string'],
                'owner' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'private' => ['type' => 'boolean'],
                'auto_init' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $endpoint = isset($config['owner']) ? "/orgs/{$config['owner']}/repos" : '/user/repos';

        $data = $this->post($run, $endpoint, $config, [
            'name' => $config['name'],
            'description' => $config['description'] ?? null,
            'private' => $config['private'] ?? false,
            'auto_init' => $config['auto_init'] ?? false,
        ]);

        return [
            'id' => $data['id'] ?? null,
            'full_name' => $data['full_name'] ?? null,
            'private' => $data['private'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
