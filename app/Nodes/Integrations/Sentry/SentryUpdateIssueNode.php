<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryUpdateIssueNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_issue';
    }

    public function name(): string
    {
        return 'Sentry: Update Issue';
    }

    public function description(): string
    {
        return 'Changes an issue\'s status, assignee, priority and other settings filled in here.';
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
                'status' => Field::select('Status', ['resolved' => 'Resolved', 'resolvedInNextRelease' => 'Resolved in next release', 'unresolved' => 'Unresolved', 'ignored' => 'Ignored']),
                'assigned_to' => Field::text('Assign to', 'A username or email, or team:<id>.'),
                'priority' => Field::select('Priority', ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High']),
                'substatus' => Field::advanced(Field::select('Substatus', ['archived_until_escalating' => 'Archived until escalating', 'archived_until_condition_met' => 'Archived until condition met', 'archived_forever' => 'Archived forever', 'escalating' => 'Escalating', 'ongoing' => 'Ongoing', 'regressed' => 'Regressed', 'new' => 'New'])),
                'is_bookmarked' => Field::advanced(Field::boolean('Bookmarked')),
                'is_subscribed' => Field::advanced(Field::boolean('Subscribed')),
                'has_seen' => Field::advanced(Field::boolean('Seen')),
                'is_public' => Field::advanced(Field::boolean('Public')),
                'status_details' => Field::advanced(Field::json('Status details', 'Like {"inRelease":"latest"} or {"ignoreDuration":60}.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->filledOf($config, ['status' => 'status', 'substatus' => 'substatus', 'assigned_to' => 'assignedTo', 'priority' => 'priority']);

        foreach (['is_public' => 'isPublic', 'has_seen' => 'hasSeen', 'is_bookmarked' => 'isBookmarked', 'is_subscribed' => 'isSubscribed'] as $key => $field) {
            if (isset($config[$key]) && $config[$key] !== '') {
                $body[$field] = (bool) $config[$key];
            }
        }

        if (($details = $this->jsonOf($config, 'status_details', 'Status details')) !== null) {
            $body['statusDetails'] = $details;
        }

        if ($body === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeIssue($this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/issues/'.$this->issueId($config).'/', [], $body));
    }
}
