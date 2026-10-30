<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryAssignIssueNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_assign_issue';
    }

    public function name(): string
    {
        return 'Sentry: Assign Issue';
    }

    public function description(): string
    {
        return 'Assigns an issue to a person or a team, or clears the assignee.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'assigned_to' => Field::text('Assign to', 'A username or email, or team:<id>. Empty clears it.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeIssue($this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/issues/'.$this->issueId($config).'/', [], ['assignedTo' => $this->text($config, 'assigned_to') ?? '']));
    }
}
