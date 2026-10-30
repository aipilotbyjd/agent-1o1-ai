<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloSearchOrganizationsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_search_organizations';
    }

    public function name(): string
    {
        return 'Apollo: Search Organizations';
    }

    public function description(): string
    {
        return 'Searches Apollo\'s database of companies by name, industry, size, location and funding. Uses Apollo credits.';
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
                'q_organization_name' => Field::text('Company name'),
                'q_organization_keyword_tags' => Field::list('Keywords', 'Like sales strategy or lead.'),
                'organization_locations' => Field::list('Locations'),
                'organization_num_employees_ranges' => Field::list('Sizes', 'Ranges like 1,10 or 250,500.'),
                'q_organization_domains_list' => Field::advanced(Field::list('Domains')),
                'organization_ids' => Field::advanced(Field::list('Company ids')),
                'more_filters' => Field::advanced(Field::json('More filters', 'Any other filter of Apollo\'s company search, by its name, like latest_funding_amount_range[min].')),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = [...$this->moreFilters($config), ...$this->lists($config, ['q_organization_keyword_tags', 'organization_locations', 'organization_num_employees_ranges', 'q_organization_domains_list', 'organization_ids']), ...$this->only($config, ['q_organization_name']), ...$this->paging($config)];

        $body = $this->apollo($run, $config, 'POST', '/mixed_companies/search', $query);

        // Companies the team already saved come back apart, as accounts.
        return $this->listResult(['organizations' => [...array_values((array) ($body['accounts'] ?? [])), ...array_values((array) ($body['organizations'] ?? []))], 'pagination' => $body['pagination'] ?? null], 'organizations');
    }
}
