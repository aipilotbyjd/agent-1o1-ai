<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomCreateConversationNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_create_conversation';
    }

    public function name(): string
    {
        return 'Intercom: Create Contact Initiated Conversation';
    }

    public function description(): string
    {
        return 'Starts a conversation as a contact, like an incoming message from them.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['body'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id', 'Or give their email or external id below.'),
                'contact_type' => Field::advanced(Field::select('They are a', ['user' => 'User', 'lead' => 'Lead'], null, 'user')),
                'email' => Field::advanced(Field::text('Email')),
                'external_id' => Field::advanced(Field::text('External id')),
                'subject' => Field::advanced(Field::text('Subject')),
                'body' => Field::textarea('Message'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $from = $this->present(['type' => ($config['contact_type'] ?? 'user') === 'lead' ? 'lead' : 'user', 'id' => $this->text($config, 'contact_id'), 'email' => filled($config['email'] ?? null) ? $this->email($config) : null, 'user_id' => $this->text($config, 'external_id')]);
        if (count($from) < 2) {
            throw new \InvalidArgumentException('Say who the message is from: a contact id, an email or an external id.');
        }

        return $this->intercom($run, $config, 'POST', '/conversations', [], $this->present(['from' => $from, 'body' => $this->requiredString($config, 'body', 'The message'), 'subject' => $this->text($config, 'subject')]));
    }
}
