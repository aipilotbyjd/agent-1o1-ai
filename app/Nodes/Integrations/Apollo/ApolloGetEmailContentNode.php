<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetEmailContentNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_email_content';
    }

    public function name(): string
    {
        return 'Apollo: Get Email Content';
    }

    public function description(): string
    {
        return 'Reads the text of one or more emails.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'email_ids' => Field::list('Email ids'),
                'body_format' => Field::select('Format', ['plain' => 'Plain text', 'html' => 'HTML'], null, 'plain'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'POST', '/emailer_messages/get_content', [], ['ids' => $this->requiredList($config, 'email_ids', 'The email ids'), ...$this->only($config, ['body_format'])]), 'emailer_messages');
    }
}
