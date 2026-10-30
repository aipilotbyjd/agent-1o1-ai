<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsCreateAdSetNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_create_ad_set';
    }

    public function name(): string
    {
        return 'Meta Ads: Create Ad Set';
    }

    public function description(): string
    {
        return 'Creates an ad set under a campaign, with its budget, schedule, goal and audience. Paused by default.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'campaign_id', 'name', 'billing_event', 'optimization_goal', 'targeting'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_id' => Field::dynamic('Campaign', 'meta_ads.campaigns', null, null, ['account_id']),
                'name' => Field::text('Name'),
                'status' => Field::select('Status', ['PAUSED' => 'Paused (spends nothing)', 'ACTIVE' => 'Active (spends money)'], null, 'PAUSED'),
                'daily_budget' => Field::integer('Daily budget', 'In the smallest unit of the currency, like cents. Leave out when the campaign holds the budget.'),
                'lifetime_budget' => Field::integer('Lifetime budget', 'In the smallest unit of the currency. Needs an end time.'),
                'billing_event' => Field::select('Pay for', ['IMPRESSIONS' => 'Impressions', 'LINK_CLICKS' => 'Link clicks', 'POST_ENGAGEMENT' => 'Post engagement', 'PAGE_LIKES' => 'Page likes', 'THRUPLAY' => 'ThruPlay', 'APP_INSTALLS' => 'App installs', 'OFFER_CLAIMS' => 'Offer claims', 'PURCHASE' => 'Purchase', 'LISTING_INTERACTION' => 'Listing interaction'], null, 'IMPRESSIONS'),
                'optimization_goal' => Field::text('Optimize for', 'Like LINK_CLICKS, REACH, OFFSITE_CONVERSIONS, LEAD_GENERATION or THRUPLAY.', 'LINK_CLICKS'),
                'targeting' => Field::json('Targeting', 'The Marketing API\'s targeting spec, like geo_locations, age_min and publisher_platforms.'),
                'start_time' => Field::datetime('Start'),
                'end_time' => Field::datetime('End'),
                'bid_strategy' => Field::advanced(Field::select('Bid strategy', ['LOWEST_COST_WITHOUT_CAP' => 'Lowest cost', 'LOWEST_COST_WITH_BID_CAP' => 'Bid cap', 'COST_CAP' => 'Cost cap', 'LOWEST_COST_WITH_MIN_ROAS' => 'Minimum return on ad spend'])),
                'bid_amount' => Field::advanced(Field::integer('Bid', 'In the smallest unit of the currency. Needed for a bid cap or cost cap.')),
                'promoted_object' => Field::advanced(Field::json('Promoted object', 'What the ads promote, like page_id or pixel_id, when the goal needs it.')),
                'dsa_payor' => Field::advanced(Field::text('EU: who pays', 'Needed when targeting the EU.')),
                'dsa_beneficiary' => Field::advanced(Field::text('EU: who benefits', 'Needed when targeting the EU.')),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field of the Marketing API\'s ad set.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $params = [
            ...$this->jsonSetting($config, 'more_fields', 'More fields'),
            'name' => $this->requiredString($config, 'name', 'The name'),
            'campaign_id' => $this->nodeId($config, 'campaign_id', 'The campaign'),
            'status' => $this->statusOf($config),
            'billing_event' => $this->requiredString($config, 'billing_event', 'What to pay for'),
            'optimization_goal' => $this->requiredString($config, 'optimization_goal', 'The goal'),
            'targeting' => $this->jsonSetting($config, 'targeting', 'The targeting', true),
            'promoted_object' => $this->jsonSetting($config, 'promoted_object', 'The promoted object') ?: null,
            ...$this->only($config, ['daily_budget', 'lifetime_budget', 'start_time', 'end_time', 'bid_strategy', 'bid_amount', 'dsa_payor', 'dsa_beneficiary']),
        ];

        $created = $this->graph($run, $config, 'POST', '/'.$this->accountPath($config).'/adsets', $params);

        return ['id' => $created['id'] ?? null, 'status' => $params['status']];
    }
}
