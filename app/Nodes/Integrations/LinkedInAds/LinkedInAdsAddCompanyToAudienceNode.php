<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsAddCompanyToAudienceNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_add_company_to_audience';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Add Company to Audience';
    }

    public function description(): string
    {
        return 'Adds a company to an audience.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['segment_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'segment_id' => Field::dynamic('Audience', 'linkedin_ads.segments', null, null, ['account_id']),
                'company_name' => Field::text('Company name'),
                'company_website_domain' => Field::text('Website domain', 'Like linkedin.com.'),
                'company_page_url' => Field::text('Company page address'),
                'organization_urn' => Field::advanced(Field::text('Company URN', 'Like urn:li:organization:123.')),
                'company_email_domain' => Field::advanced(Field::text('Email domain')),
                'stock_symbol' => Field::advanced(Field::text('Stock symbol')),
                'industries' => Field::advanced(Field::list('Industries')),
                'city' => Field::advanced(Field::text('City')),
                'state' => Field::advanced(Field::text('State')),
                'country' => Field::advanced(Field::text('Country', 'Two letters, like US.')),
                'postal_code' => Field::advanced(Field::text('Postal code')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'segment_id', 'The audience');

        $this->linkedin($run, $config, 'POST', "/dmpSegments/{$id}/companies", '', $this->audienceCompany($config, 'ADD'));

        return ['added' => true, 'audience_id' => $id];
    }
}
