<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

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
                'state' => Field::select('State', ['open' => 'Open', 'closed' => 'Closed', 'all' => 'All'], default: 'open'),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
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
