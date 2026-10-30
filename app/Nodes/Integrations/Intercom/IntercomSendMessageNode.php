<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomSendMessageNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_send_message';
    }

    public function name(): string
    {
        return 'Intercom: Send Message To Contact';
    }

    public function description(): string
    {
        return 'Sends a new in-app or email message to a contact from a teammate. This is an admin-initiated conversation.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'message_type' => Field::select('Send by', ['in_app' => 'In the messenger', 'email' => 'Email'], null, 'in_app'),
                'contact_id' => Field::text('Contact id'),
                'contact_type' => Field::advanced(Field::select('They are a', ['user' => 'User', 'lead' => 'Lead'], null, 'user')),
                'subject' => Field::text('Subject', 'Needed for email.'),
                'body' => Field::textarea('Message'),
                'template' => Field::advanced(Field::select('Email template', ['plain' => 'Plain', 'personal' => 'Personal'], null, 'plain')),
                'admin_id' => Field::advanced(Field::dynamic('From teammate', 'intercom.admins')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $type = ($config['message_type'] ?? 'in_app') === 'email' ? 'email' : 'in_app';
        $to = ['type' => ($config['contact_type'] ?? 'user') === 'lead' ? 'lead' : 'user', 'id' => $this->safeId($config, 'contact_id', 'The contact')];
        $from = ['type' => 'admin', 'id' => (int) $this->adminId($run, $config)];

        return $this->intercom($run, $config, 'POST', '/messages', [], $this->present(['message_type' => $type, 'subject' => $this->text($config, 'subject'), 'body' => $this->requiredString($config, 'body', 'The message'), 'template' => $type === 'email' ? (($config['template'] ?? 'plain') === 'personal' ? 'personal' : 'plain') : null, 'from' => $from, 'to' => $to, 'create_conversation_without_contact_reply' => true]));
    }
}
