<?php

namespace App\Nodes\Integrations\GitHub;

use App\Models\Runs\Run;

class GitHubCreateIssueNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_issue';
    }

    public function name(): string
    {
        return 'GitHub: Create Issue';
    }

    public function description(): string
    {
        return 'Creates a new issue in a repository.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'title'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'repo' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'labels' => ['type' => 'array'],
                'assignees' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $data = $this->post($run, "/repos/{$config['repo']}/issues", $config, [
            'title' => $config['title'],
            'body' => $config['body'] ?? '',
            'labels' => $config['labels'] ?? [],
            'assignees' => $config['assignees'] ?? [],
        ]);

        return [
            'id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
            'title' => $data['title'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
