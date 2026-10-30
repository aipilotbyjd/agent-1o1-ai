<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetIssueEventNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_issue_event';
    }

    public function name(): string
    {
        return 'Sentry: Get Issue Event';
    }

    public function description(): string
    {
        return 'Gets an event of an issue with its stack trace: a given one, or the latest, oldest or recommended.';
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
                'which' => Field::select('Which', ['recommended' => 'Recommended', 'latest' => 'Latest', 'oldest' => 'Oldest'], null, 'recommended'),
                'event_id' => Field::text('Or this event id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $which = $this->text($config, 'event_id') ?? $this->text($config, 'which') ?? 'recommended';
        $event = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/issues/'.$this->issueId($config).'/events/'.$this->slug($which, 'The event id').'/');

        return $event === null ? ['found' => false] : ['found' => true, ...$this->shapeEvent($event)];
    }
}
