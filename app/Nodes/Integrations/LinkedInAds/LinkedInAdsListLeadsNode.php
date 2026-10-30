<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsListLeadsNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_leads';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List Lead Form Responses';
    }

    public function description(): string
    {
        return 'Reads the leads of an ad account or company page, of one kind or form.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['lead_type'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->formOwnerFields(),
                'lead_type' => Field::select('Kind', ['SPONSORED' => 'From ads', 'COMPANY' => 'From a company page', 'EVENT' => 'From an event'], null, 'SPONSORED'),
                'form_id' => Field::advanced(Field::dynamic('Only this form', 'linkedin_ads.lead_forms', null, null, ['account_id'])),
                'form_version' => Field::advanced(Field::integer('Form version', 'With a form: the version of it to read. LinkedIn counts from 1.', 1, 1)),
                'test_leads_only' => Field::advanced(Field::boolean('Only test leads')),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $leads = array_map($this->shapeLead(...), $this->readLeads($run, $config, $this->requiredString($config, 'lead_type', 'The kind')));

        return ['leads' => $leads, 'count' => count($leads)];
    }
}
