<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetPersonNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_person';
    }

    public function name(): string
    {
        return 'Apollo: Get Person';
    }

    public function description(): string
    {
        return 'Reads the complete profile of a person in Apollo\'s database by their id. Uses Apollo credits.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['person_id'],
            'properties' => [
                ...$this->credentialFields(),
                'person_id' => Field::text('Person id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'GET', '/people/'.$this->recordId($config, 'person_id', 'The person id'));

        return ['person' => $body['person'] ?? $body];
    }
}
