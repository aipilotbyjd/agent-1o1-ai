<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListRepositoryCommitsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_repository_commits';
    }

    public function name(): string
    {
        return 'Sentry: List Repository Commits';
    }

    public function description(): string
    {
        return 'Lists the commits Sentry knows of in a repository.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repository_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'repository_id' => Field::text('Repository id', 'From List Repositories.'),
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/repos/'.$this->pathId($config, 'repository_id', 'The repository').'/commits/', 'commits');
    }
}
