<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloFindContactNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_find_contact';
    }

    public function name(): string
    {
        return 'Apollo: Find Contact by Email';
    }

    public function description(): string
    {
        return 'Finds a saved contact by email address. If several share it, the one created first is returned.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $match = $this->contactByEmail($run, $config, strtolower($this->requiredString($config, 'email', 'The email')));

        return ['found' => $match !== null, 'contact' => $match === null ? null : $this->shapeContact($match)];
    }
}
