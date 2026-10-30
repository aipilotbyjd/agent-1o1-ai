<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetApiUsageNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_api_usage';
    }

    public function name(): string
    {
        return 'Apollo: Get API Usage';
    }

    public function description(): string
    {
        return 'Reads how many calls the key has made and the rate limits. Needs a master API key.';
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
                'endpoints' => Field::advanced(Field::list('Endpoints', 'Only these, like api/v1/contacts/search.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->apollo($run, $config, 'POST', '/usage_stats/api_usage_stats', ['endpoints' => $this->stringList($config, 'endpoints')]);
    }
}
