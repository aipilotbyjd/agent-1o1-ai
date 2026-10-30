<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryDeleteIssueNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_issue';
    }

    public function name(): string
    {
        return 'Sentry: Delete Issue';
    }

    public function description(): string
    {
        return 'Removes an issue and its events.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $issue = $this->issueId($config);
        $this->sentry($run, $config, 'DELETE', '/api/0/organizations/'.$this->org($run, $config)."/issues/{$issue}/");

        return ['deleted' => true, 'id' => $issue];
    }
}
