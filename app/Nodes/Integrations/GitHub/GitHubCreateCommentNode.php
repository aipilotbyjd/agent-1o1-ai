<?php

namespace App\Nodes\Integrations\GitHub;

use App\Models\Runs\Run;

class GitHubCreateCommentNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_create_comment';
    }

    public function name(): string
    {
        return 'GitHub: Create Comment';
    }

    public function description(): string
    {
        return 'Adds a comment to an issue or pull request.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo', 'issue_number', 'body'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'repo' => ['type' => 'string'],
                'issue_number' => ['type' => 'integer'],
                'body' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $data = $this->post($run, "/repos/{$config['repo']}/issues/{$config['issue_number']}/comments", $config, [
            'body' => $config['body'],
        ]);

        return [
            'id' => $data['id'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
