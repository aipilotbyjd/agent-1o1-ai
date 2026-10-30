<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloSendEmailNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_send_email';
    }

    public function name(): string
    {
        return 'Apollo: Send Email Now';
    }

    public function description(): string
    {
        return 'Sends an email draft at once. It reaches the contact and cannot be taken back.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email_id'],
            'properties' => [
                ...$this->credentialFields(),
                'email_id' => Field::text('Email id', 'From Create Email Draft.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'POST', '/emailer_messages/'.$this->recordId($config, 'email_id', 'The email id').'/send_now', [], []);

        return ['sent' => true, 'email' => $body['emailer_message'] ?? $body];
    }
}
