<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloCheckEmailStatusNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_check_email_status';
    }

    public function name(): string
    {
        return 'Apollo: Check Email Send Status';
    }

    public function description(): string
    {
        return 'Reads whether an email has gone out, or why it has not.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email_id'],
            'properties' => [
                ...$this->credentialFields(),
                'email_id' => Field::text('Email id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->apollo($run, $config, 'POST', '/emailer_messages/email_send_status', [], ['id' => $this->recordId($config, 'email_id', 'The email id')]);
    }
}
