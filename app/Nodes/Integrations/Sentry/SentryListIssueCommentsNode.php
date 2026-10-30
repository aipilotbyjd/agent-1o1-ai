<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

/**
 * Not in the public API reference: this is the endpoint Sentry's own app and MCP server use.
 */
class SentryListIssueCommentsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_issue_comments';
    }

    public function name(): string
    {
        return 'Sentry: List Issue Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on an issue.';
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
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/issues/'.$this->issueId($config).'/notes/', 'comments', [], fn (array $note): array => ['id' => $note['id'] ?? null, 'text' => data_get($note, 'data.text'), 'user' => data_get($note, 'user.email') ?? data_get($note, 'user.name'), 'created_at' => $note['dateCreated'] ?? null]);
    }
}
