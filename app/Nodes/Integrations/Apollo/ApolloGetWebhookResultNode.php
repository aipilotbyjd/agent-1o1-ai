<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetWebhookResultNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_webhook_result';
    }

    public function name(): string
    {
        return 'Apollo: Get Webhook Result';
    }

    public function description(): string
    {
        return 'Reads the answer of a call that delivers later, like a revealed phone number, by its request id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['request_id'],
            'properties' => [
                ...$this->credentialFields(),
                'request_id' => Field::text('Request id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->apollo($run, $config, 'GET', '/webhook_result/'.$this->recordId($config, 'request_id', 'The request id'));
    }
}
