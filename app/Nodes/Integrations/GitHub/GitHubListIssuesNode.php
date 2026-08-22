<?php

namespace App\Nodes\Integrations\GitHub;

use App\Models\Runs\Run;

class GitHubListIssuesNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_list_issues';
    }

    public function name(): string
    {
        return 'GitHub: List Issues';
    }

    public function description(): string
    {
        return 'Lists issues in a repository.';
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
            'properties' => ['issues' => ['type' => 'array', 'items' => ['type' => 'object']]],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['issues' => $this->get($run, "/repos/{$config['repo']}/issues", $config, [
            'state' => $config['state'] ?? 'open',
            'per_page' => $config['per_page'] ?? 30,
        ])];
    }
}
