<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloCreateEmailDraftNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_create_email_draft';
    }

    public function name(): string
    {
        return 'Apollo: Create Email Draft';
    }

    public function description(): string
    {
        return 'Writes a one-off email to a contact as a draft. Nothing is sent until Send Email Now.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::dynamic('Contact', 'apollo.contacts'),
                'subject' => Field::text('Subject'),
                'body_html' => Field::textarea('Body', 'HTML is allowed.'),
                'emailer_template_id' => Field::advanced(Field::text('Template id')),
                'in_response_to_emailer_message_id' => Field::advanced(Field::text('In reply to email id')),
                'enable_tracking' => Field::advanced(Field::boolean('Track opens and clicks', null, true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'POST', '/emailer_messages', [], ['contact_id' => $this->recordId($config, 'contact_id', 'The contact'), ...$this->only($config, ['subject', 'body_html', 'emailer_template_id', 'in_response_to_emailer_message_id', 'enable_tracking'])]);

        return ['email' => $body['emailer_message'] ?? $body];
    }
}
