<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryBulkUpdateIssuesNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_bulk_update_issues';
    }

    public function name(): string
    {
        return 'Sentry: Update Issues In Bulk';
    }

    public function description(): string
    {
        return 'Changes several issues at once, by their numeric ids.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'issue_ids' => Field::list('Issue ids', 'Numeric ids.'),
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
        $ids = $this->items($config, 'issue_ids');

        if ($ids === [] || array_filter($ids, fn (string $id): bool => ! ctype_digit($id)) !== []) {
            throw new InvalidArgumentException('Issue ids must be numbers.');
        }

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

        return $this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/issues/', ['id' => $ids], $body);
    }
}
