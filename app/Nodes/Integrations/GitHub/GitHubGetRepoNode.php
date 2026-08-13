<?php

namespace App\Nodes\Integrations\GitHub;

use App\Models\Runs\Run;

class GitHubGetRepoNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_get_repo';
    }

    public function name(): string
    {
        return 'GitHub: Get Repository';
    }

    public function description(): string
    {
        return 'Gets details of a specific repository.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, "/repos/{$config['repo']}", $config);
    }
}
