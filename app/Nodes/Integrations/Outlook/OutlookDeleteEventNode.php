<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class OutlookDeleteEventNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_delete_event';
    }

    public function name(): string
    {
        return 'Outlook: Delete Event';
    }

    public function description(): string
    {
        return 'Deletes a calendar event.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_id'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'string'],
                'event_id' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->delete($run, '/me/events/'.rawurlencode((string) $config['event_id']), $config);
    }
}
