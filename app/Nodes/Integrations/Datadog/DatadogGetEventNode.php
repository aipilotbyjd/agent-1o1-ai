<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetEventNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_event';
    }

    public function name(): string
    {
        return 'Datadog: Get Event';
    }

    public function description(): string
    {
        return 'Gets one event by its id.';
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
                'event_id' => Field::text('Event id', 'The "id" of an event from List Events.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $event = $this->find($run, $config, '/api/v2/events/'.$this->pathId($config, 'event_id', 'The event id'));

        return $event === null ? ['found' => false] : ['found' => true, ...$this->shapeEvent((array) ($event['data'] ?? []))];
    }
}
