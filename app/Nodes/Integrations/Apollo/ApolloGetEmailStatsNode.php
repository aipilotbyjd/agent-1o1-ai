<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetEmailStatsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_email_stats';
    }

    public function name(): string
    {
        return 'Apollo: Get Email Stats';
    }

    public function description(): string
    {
        return 'Reads the opens, clicks and replies of an email.';
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
        return $this->apollo($run, $config, 'GET', '/emailer_messages/'.$this->recordId($config, 'email_id', 'The email id').'/activities');
    }
}
