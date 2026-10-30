<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateThreadNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_thread';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Thread';
    }

    public function description(): string
    {
        return 'Starts a collaboration thread on a ticket. Needs a Pro plan or higher.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'type'],
            'properties' => [
                ...$this->credentialFields(),
                'ticket_id' => Field::text('Ticket id'),
                'type' => Field::select('Type', ['forward' => 'Forward', 'discussion' => 'Discussion'], null, 'forward'),
                'title' => Field::advanced(Field::text('Title')),
                'email_config_id' => Field::advanced(Field::dynamic('From address', 'freshdesk.email_configs')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = (string) $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->freshdesk($run, $config, 'POST', '/collaboration/threads', [], $this->present(['type' => $this->requiredString($config, 'type', 'The type'), 'title' => $this->text($config, 'title'), 'parent' => ['id' => $id, 'type' => 'ticket'], 'additional_info' => filled($config['email_config_id'] ?? null) ? ['email_config_id' => (int) $this->numericId($config, 'email_config_id', 'The from address')] : null]));
    }
}
