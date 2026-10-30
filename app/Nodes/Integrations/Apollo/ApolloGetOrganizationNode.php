<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloGetOrganizationNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_get_organization';
    }

    public function name(): string
    {
        return 'Apollo: Get Organization';
    }

    public function description(): string
    {
        return 'Reads the complete details of a company in Apollo\'s database by its id. Uses Apollo credits.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['organization_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization_id' => Field::text('Organization id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'GET', '/organizations/'.$this->recordId($config, 'organization_id', 'The organization id'));

        return ['organization' => $body['organization'] ?? $body];
    }
}
