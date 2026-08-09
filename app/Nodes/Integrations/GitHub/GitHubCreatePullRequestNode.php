<?php

namespace App\Nodes\Integrations\GitHub;

use App\Models\Runs\Run;

class GitHubCreatePullRequestNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_pull_request';
    }

    public function name(): string
    {
        return 'GitHub: Create Pull Request';
    }

    public function description(): string
    {
        return 'Creates a new pull request in a repository.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['access_token', 'repo', 'title', 'head', 'base'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'repo' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'head' => ['type' => 'string'],
                'base' => ['type' => 'string'],
                'body' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $data = $this->post("/repos/{$config['repo']}/pulls", $config, [
            'title' => $config['title'],
            'head' => $config['head'],
            'base' => $config['base'],
            'body' => $config['body'] ?? '',
        ]);

        return [
            'id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
            'title' => $data['title'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
