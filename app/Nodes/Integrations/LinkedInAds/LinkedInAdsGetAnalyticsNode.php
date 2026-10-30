<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsGetAnalyticsNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_get_analytics';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Create Report';
    }

    public function description(): string
    {
        return 'Reports spend, impressions, clicks, leads and more over a date range for accounts, campaign groups, campaigns or creatives, broken down one way.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pivot'],
            'properties' => [
                ...$this->credentialFields(),
                'pivot' => Field::select('Break down by', ['ACCOUNT' => 'Account', 'CAMPAIGN_GROUP' => 'Campaign group', 'CAMPAIGN' => 'Campaign', 'CREATIVE' => 'Creative', 'COMPANY' => 'Company', 'SHARE' => 'Sponsored post', 'CONVERSION' => 'Conversion', 'MEMBER_COMPANY' => 'Member company', 'MEMBER_COUNTRY_V2' => 'Member country', 'MEMBER_REGION_V2' => 'Member region', 'MEMBER_JOB_TITLE' => 'Member job title', 'MEMBER_JOB_FUNCTION' => 'Member job function', 'MEMBER_SENIORITY' => 'Member seniority', 'MEMBER_INDUSTRY' => 'Member industry', 'MEMBER_COMPANY_SIZE' => 'Member company size'], null, 'CAMPAIGN'),
                'account_ids' => Field::list('Ad account ids'),
                'campaign_group_ids' => Field::list('Campaign group ids'),
                'campaign_ids' => Field::list('Campaign ids'),
                'creative_ids' => Field::advanced(Field::list('Creative ids')),
                'date_from' => Field::text('From', 'YYYY-MM-DD. Empty is 30 days ago.'),
                'date_to' => Field::text('To', 'YYYY-MM-DD. Empty is today.'),
                'time_granularity' => Field::select('Group by time', ['ALL' => 'Whole range', 'DAILY' => 'Day', 'MONTHLY' => 'Month', 'YEARLY' => 'Year'], null, 'ALL'),
                'fields' => Field::list('Metrics', 'Up to 20. Empty reads impressions, clicks, costInLocalCurrency, landingPageClicks, externalWebsiteConversions, oneClickLeads, likes, comments, shares and follows.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = 'q=analytics&pivot='.rawurlencode($this->requiredString($config, 'pivot', 'The breakdown')).$this->analyticsQuery($config);

        return $this->elements($this->linkedin($run, $config, 'GET', '/adAnalytics', $query), 'rows');
    }
}
