<?php

namespace App\Nodes\Integrations\GitHub;

use App\Models\Runs\Run;

class GitHubListPullRequestsNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_list_pull_requests';
    }

    public function name(): string
    {
        return 'GitHub: List Pull Requests';
    }

    public function description(): string
    {
        return 'Lists pull requests in a repository.';
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
                'state' => ['type' => 'string'],
                'per_page' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(array $config = []): array
    {
        return [
            'type' => 'object',
            'properties' => ['pull_requests' => ['type' => 'array', 'items' => ['type' => 'object']]],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['pull_requests' => $this->get($run, "/repos/{$config['repo']}/pulls", $config, [
            'state' => $config['state'] ?? 'open',
            'per_page' => $config['per_page'] ?? 30,
        ])];
    }
}
