<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaGetEventNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_get_event';
    }

    public function name(): string
    {
        return 'Exa: Get Websets Event';
    }

    public function description(): string
    {
        return 'Reads one event from the team\'s websets.';
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
                'event_id' => Field::text('Event id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['event' => $this->exa($run, $config, 'GET', self::WEBSETS.'/events/'.$this->pathId($config, 'event_id', 'The event id'))];
    }
}
