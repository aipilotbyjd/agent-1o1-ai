<?php

namespace App\Nodes\Integrations\GitHub;

use App\Models\Runs\Run;

class GitHubListCommitsNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_list_commits';
    }

    public function name(): string
    {
        return 'GitHub: List Commits';
    }

    public function description(): string
    {
        return 'Lists commits in a repository.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'repo' => ['type' => 'string'],
                'sha' => ['type' => 'string'],
                'path' => ['type' => 'string'],
                'per_page' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['commits' => $this->get($run, "/repos/{$config['repo']}/commits", $config, [
            'sha' => $config['sha'] ?? null,
            'path' => $config['path'] ?? null,
            'per_page' => $config['per_page'] ?? 30,
        ])];
    }
}
