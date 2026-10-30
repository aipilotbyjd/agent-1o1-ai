<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

/**
 * Not in the public API reference: this is the endpoint Sentry's own app and MCP server use.
 */
class SentryAddIssueCommentNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_add_issue_comment';
    }

    public function name(): string
    {
        return 'Sentry: Add Issue Comment';
    }

    public function description(): string
    {
        return 'Adds a comment to an issue.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'issue_id' => Field::text('Issue', 'Its numeric id or short id, like BACKEND-1A.', 'BACKEND-1A'),
                'text' => Field::textarea('Comment'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $note = $this->sentry($run, $config, 'POST', '/api/0/organizations/'.$this->org($run, $config).'/issues/'.$this->issueId($config).'/notes/', [], ['text' => $this->requiredString($config, 'text', 'The comment')]);

        return ['id' => $note['id'] ?? null, 'text' => data_get($note, 'data.text'), 'created_at' => $note['dateCreated'] ?? null];
    }
}
