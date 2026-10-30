<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryResolveIssueNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_resolve_issue';
    }

    public function name(): string
    {
        return 'Sentry: Resolve Issue';
    }

    public function description(): string
    {
        return 'Marks an issue resolved, now or in the next release.';
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
                'in_next_release' => Field::boolean('Only from the next release'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $status = ($config['in_next_release'] ?? false) ? 'resolvedInNextRelease' : 'resolved';

        return $this->shapeIssue($this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/issues/'.$this->issueId($config).'/', [], ['status' => $status]));
    }
}
