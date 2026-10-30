<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ApolloEnrichOrganizationNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_enrich_organization';
    }

    public function name(): string
    {
        return 'Apollo: Enrich Organization';
    }

    public function description(): string
    {
        return 'Finds a company by domain, website, LinkedIn address or name and returns its details. Uses Apollo credits.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'domain' => Field::text('Domain', 'Like apollo.io, without www.'),
                'website' => Field::advanced(Field::text('Website')),
                'linkedin_url' => Field::advanced(Field::text('LinkedIn address')),
                'name' => Field::advanced(Field::text('Name')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = $this->only($config, ['domain', 'website', 'linkedin_url', 'name']);

        if ($query === []) {
            throw new InvalidArgumentException('Give at least a domain, a website, a LinkedIn address or a name.');
        }

        $organization = $this->apollo($run, $config, 'GET', '/organizations/enrich', $query)['organization'] ?? null;

        return ['found' => filled($organization), 'organization' => $organization];
    }
}
