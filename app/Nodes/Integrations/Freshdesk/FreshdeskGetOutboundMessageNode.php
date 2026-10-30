<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetOutboundMessageNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_outbound_message';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Outbound Message Status';
    }

    public function description(): string
    {
        return 'Reads the delivery status of an outbound message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::text('Message id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'message_id', 'The message');

        return $this->findOne($run, $config, "/channels/outbound-messages/{$id}", $id);
    }
}
