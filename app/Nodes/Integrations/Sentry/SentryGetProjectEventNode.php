<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetProjectEventNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_project_event';
    }

    public function name(): string
    {
        return 'Sentry: Get Project Event';
    }

    public function description(): string
    {
        return 'Gets an event of a project with its stack trace.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'event_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
                'event_id' => Field::text('Event id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $event = $this->find($run, $config, '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/events/'.$this->pathId($config, 'event_id', 'The event id').'/');

        return $event === null ? ['found' => false] : ['found' => true, ...$this->shapeEvent($event)];
    }
}
