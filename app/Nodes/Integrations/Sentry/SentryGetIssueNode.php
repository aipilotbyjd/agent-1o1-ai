<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetIssueNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_issue';
    }

    public function name(): string
    {
        return 'Sentry: Get Issue';
    }

    public function description(): string
    {
        return 'Gets an issue.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'issue_id' => Field::text('Issue', 'Its numeric id or short id, like BACKEND-1A.', 'BACKEND-1A'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $issue = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/issues/'.$this->issueId($config).'/');

        return $issue === null ? ['found' => false] : ['found' => true, ...$this->shapeIssue($issue), 'tags' => $issue['tags'] ?? null, 'activity' => $issue['activity'] ?? null, 'first_release' => data_get($issue, 'firstRelease.version'), 'last_release' => data_get($issue, 'lastRelease.version')];
    }
}
