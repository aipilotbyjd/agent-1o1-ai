<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetCurrentUserNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_current_user';
    }

    public function name(): string
    {
        return 'Apollo: Get Current User';
    }

    public function description(): string
    {
        return 'Reads the profile of the key\'s user and how many credits are left, and checks the connection works.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'include_credit_usage' => Field::advanced(Field::boolean('Include credit usage', null, true)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->apollo($run, $config, 'GET', '/users/api_profile', ['include_credit_usage' => (bool) ($config['include_credit_usage'] ?? true)]);
    }
}
