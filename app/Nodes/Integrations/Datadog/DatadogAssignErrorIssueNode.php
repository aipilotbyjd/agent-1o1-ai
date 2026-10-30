<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogAssignErrorIssueNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_assign_error_issue';
    }

    public function name(): string
    {
        return 'Datadog: Assign Error Tracking Issue';
    }

    public function description(): string
    {
        return 'Assigns an issue of Datadog Error Tracking to a user, or clears the assignee.';
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
                'issue_id' => Field::text('Issue id'),
                'user_id' => $this->userField('Assign to (empty clears it)'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'issue_id', 'The issue id');
        $user = $this->text($config, 'user_id');

        if ($user === null) {
            $this->datadog($run, $config, 'DELETE', "/api/v2/error-tracking/issues/{$id}/assignee");

            return ['id' => rawurldecode($id), 'assignee' => null];
        }

        return $this->flatten((array) ($this->datadog($run, $config, 'PUT', "/api/v2/error-tracking/issues/{$id}/assignee", [], ['data' => ['type' => 'assignee', 'id' => $user]])['data'] ?? []));
    }
}
