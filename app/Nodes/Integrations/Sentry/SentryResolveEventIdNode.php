<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryResolveEventIdNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_resolve_event_id';
    }

    public function name(): string
    {
        return 'Sentry: Find Event By Id';
    }

    public function description(): string
    {
        return 'Finds which project and issue an event id belongs to.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'event_id' => Field::text('Event id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/eventids/'.$this->pathId($config, 'event_id', 'The event id').'/');

        return $found === null ? ['found' => false] : ['found' => true, 'organization' => $found['organizationSlug'] ?? null, 'project' => $found['projectSlug'] ?? null, 'issue_id' => $found['groupId'] ?? null, 'event_id' => $found['eventId'] ?? null, 'event' => $found['event'] ?? null];
    }
}
