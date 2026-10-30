<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloSearchPeopleNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_search_people';
    }

    public function name(): string
    {
        return 'Apollo: Search People';
    }

    public function description(): string
    {
        return 'Searches Apollo\'s database of people by role, location, company and keywords. Costs no credits, and shows no email addresses or phone numbers: enrich a person to get those.';
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
                'person_titles' => Field::list('Job titles', 'For example: sales manager.'),
                'person_locations' => Field::list('Person locations', 'Cities, states or countries, like California, US.'),
                'person_seniorities' => Field::list('Seniorities', 'owner, founder, c_suite, partner, vp, head, director, manager, senior, entry or intern.'),
                'q_keywords' => Field::text('Keywords'),
                'q_person_name' => Field::text('Name'),
                'q_organization_domains_list' => Field::list('Company domains', 'Like apollo.io, without www.'),
                'organization_locations' => Field::advanced(Field::list('Company locations')),
                'organization_num_employees_ranges' => Field::advanced(Field::list('Company sizes', 'Ranges like 1,10 or 250,500.')),
                'organization_ids' => Field::advanced(Field::list('Company ids')),
                'contact_email_status' => Field::advanced(Field::list('Email status', 'verified, unverified, likely to engage or unavailable.')),
                'include_similar_titles' => Field::advanced(Field::boolean('Include similar titles', null, true)),
                'more_filters' => Field::advanced(Field::json('More filters', 'Any other filter of Apollo\'s people search, by its name, like revenue_range[min].')),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = [...$this->moreFilters($config), ...$this->lists($config, ['person_titles', 'person_locations', 'person_seniorities', 'q_organization_domains_list', 'organization_locations', 'organization_num_employees_ranges', 'organization_ids', 'contact_email_status']), ...$this->only($config, ['q_keywords', 'q_person_name']), 'include_similar_titles' => $config['include_similar_titles'] ?? null, ...$this->paging($config)];

        return $this->listResult($this->apollo($run, $config, 'POST', '/mixed_people/api_search', $query), 'people');
    }
}
