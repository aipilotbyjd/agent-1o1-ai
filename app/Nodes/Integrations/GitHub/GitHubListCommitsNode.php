<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

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

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'sha' => Field::dynamic('Branch', 'github.branches', 'Defaults to the default branch.', dependsOn: ['repo']),
                'path' => Field::advanced(Field::text('Path', 'Only commits touching this file or folder.', 'src/')),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
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
