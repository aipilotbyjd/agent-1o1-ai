<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryIgnoreIssueNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_ignore_issue';
    }

    public function name(): string
    {
        return 'Sentry: Archive Issue';
    }

    public function description(): string
    {
        return 'Archives (ignores) an issue, for good, for a while, or until it escalates.';
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
                'until' => Field::select('Until', ['archived_until_escalating' => 'It escalates', 'archived_forever' => 'Forever'], null, 'archived_until_escalating'),
                'minutes' => Field::integer('Or for this many minutes', null, null, 1),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $until = $this->text($config, 'until') ?? 'archived_until_escalating';
        $body = ['status' => 'ignored', 'substatus' => $until];

        if (filled($config['minutes'] ?? null)) {
            $body['substatus'] = 'archived_until_condition_met';
            $body['statusDetails'] = ['ignoreDuration' => (int) $config['minutes']];
        }

        return $this->shapeIssue($this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/issues/'.$this->issueId($config).'/', [], $body));
    }
}
