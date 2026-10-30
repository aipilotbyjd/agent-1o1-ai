<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinkedInAdsCreateCampaignNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_create_campaign';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Create Campaign';
    }

    public function description(): string
    {
        return 'Creates a campaign, paused by default so it spends nothing until you switch it on.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'campaign_group_id', 'name', 'type', 'cost_type', 'locale_country', 'locale_language', 'targeting_criteria'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_group_id' => Field::dynamic('Campaign group', 'linkedin_ads.campaign_groups', null, null, ['account_id']),
                'name' => Field::text('Name'),
                'status' => Field::select('Status', ['PAUSED' => 'Paused (spends nothing)', 'DRAFT' => 'Draft (spends nothing)', 'ACTIVE' => 'Active (spends money)'], null, 'PAUSED'),
                'type' => Field::select('Type', ['SPONSORED_UPDATES' => 'Sponsored content', 'TEXT_AD' => 'Text ad', 'SPONSORED_INMAILS' => 'Sponsored messaging', 'DYNAMIC' => 'Dynamic ad'], null, 'SPONSORED_UPDATES'),
                'objective_type' => Field::select('Objective', ['BRAND_AWARENESS', 'ENGAGEMENT', 'JOB_APPLICANTS', 'LEAD_GENERATION', 'WEBSITE_CONVERSIONS', 'WEBSITE_VISITS', 'VIDEO_VIEWS']),
                'cost_type' => Field::select('Pay per', ['CPM' => 'Thousand impressions', 'CPC' => 'Click', 'CPV' => 'Video view'], null, 'CPM'),
                'daily_budget' => Field::text('Daily budget', 'An amount, like 50.00. Give this or a total budget.'),
                'total_budget' => Field::text('Total budget', 'An amount for the whole campaign.'),
                'unit_cost' => Field::text('Bid', 'An amount per click or impression.'),
                'currency' => Field::text('Currency', 'Three letters, like USD. Must match the account.', 'USD'),
                'locale_country' => Field::text('Locale country', 'Two letters, like US.', 'US'),
                'locale_language' => Field::text('Locale language', 'Two letters, like en.', 'en'),
                'targeting_criteria' => Field::json('Targeting', 'LinkedIn\'s targetingCriteria object, with include and exclude.'),
                'start' => Field::datetime('Start'),
                'end' => Field::datetime('End'),
                'associated_entity' => Field::advanced(Field::text('Advertised company', 'A URN, like urn:li:organization:123. Needed for sponsored content, dynamic and lead ads.')),
                'optimization_target_type' => Field::advanced(Field::text('Optimization target', 'Like MAX_LEAD or MAX_CLICK.')),
                'offsite_delivery_enabled' => Field::advanced(Field::boolean('Serve on the LinkedIn Audience Network')),
                'audience_expansion_enabled' => Field::advanced(Field::boolean('Expand the audience')),
                'political_intent' => Field::advanced(Field::select('Political advertising', ['NOT_DECLARED', 'NOT_POLITICAL', 'POLITICAL'], 'Declared when targeting the EU.')),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field of LinkedIn\'s campaign, like format or pacingStrategy.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $account = $this->numericId($config, 'account_id', 'The ad account');
        $body = [
            ...$this->jsonSetting($config, 'more_fields', 'More fields'),
            'account' => "urn:li:sponsoredAccount:{$account}",
            'campaignGroup' => $this->urn($config, 'campaign_group_id', 'The campaign group', 'sponsoredCampaignGroup'),
            'name' => $this->requiredString($config, 'name', 'The name'),
            'status' => strtoupper((string) ($config['status'] ?? 'PAUSED')),
            'type' => $this->requiredString($config, 'type', 'The type'),
            'costType' => $this->requiredString($config, 'cost_type', 'What to pay per'),
            'locale' => ['country' => strtoupper($this->requiredString($config, 'locale_country', 'The locale country')), 'language' => strtolower($this->requiredString($config, 'locale_language', 'The locale language'))],
            'targetingCriteria' => $this->jsonSetting($config, 'targeting_criteria', 'The targeting', true),
            'runSchedule' => array_filter(['start' => $this->epochMs($config['start'] ?? now()->toIso8601String()), 'end' => filled($config['end'] ?? null) ? $this->epochMs($config['end']) : null]),
            ...$this->campaignExtras($config),
        ];

        if (! isset($body['dailyBudget']) && ! isset($body['totalBudget'])) {
            throw new InvalidArgumentException('Give a daily budget or a total budget.');
        }

        return ['created' => true, 'id' => $this->create($run, $config, "/adAccounts/{$account}/adCampaigns", $body), 'status' => $body['status']];
    }
}
