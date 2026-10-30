<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskSendInviteNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_send_invite';
    }

    public function name(): string
    {
        return 'Freshdesk: Send Contact Invite';
    }

    public function description(): string
    {
        return 'Invites a contact to the support portal.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'contact_id', 'The contact');
        $this->freshdesk($run, $config, 'PUT', "/contacts/{$id}/send_invite");

        return ['invited' => true, 'id' => (int) $id];
    }
}
