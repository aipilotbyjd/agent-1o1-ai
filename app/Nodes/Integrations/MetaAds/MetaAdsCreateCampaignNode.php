<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsCreateCampaignNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_create_campaign';
    }

    public function name(): string
    {
        return 'Meta Ads: Create Campaign';
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
            'required' => ['account_id', 'name', 'objective'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'name' => Field::text('Name'),
                'objective' => Field::select('Objective', ['OUTCOME_AWARENESS' => 'Awareness', 'OUTCOME_TRAFFIC' => 'Traffic', 'OUTCOME_ENGAGEMENT' => 'Engagement', 'OUTCOME_LEADS' => 'Leads', 'OUTCOME_APP_PROMOTION' => 'App promotion', 'OUTCOME_SALES' => 'Sales'], null, 'OUTCOME_TRAFFIC'),
                'status' => Field::select('Status', ['PAUSED' => 'Paused (spends nothing)', 'ACTIVE' => 'Active (spends money)'], null, 'PAUSED'),
                'special_ad_categories' => Field::list('Special ad categories', 'Required by Meta when they apply: EMPLOYMENT, HOUSING, CREDIT, ISSUES_ELECTIONS_POLITICS, ONLINE_GAMBLING_AND_GAMING or FINANCIAL_PRODUCTS_SERVICES.'),
                'daily_budget' => Field::advanced(Field::integer('Daily budget', 'In the smallest unit of the currency, like cents. Empty leaves budgets to the ad sets.')),
                'lifetime_budget' => Field::advanced(Field::integer('Lifetime budget', 'In the smallest unit of the currency.')),
                'spend_cap' => Field::advanced(Field::integer('Spending limit', 'In the smallest unit of the currency.')),
                'bid_strategy' => Field::advanced(Field::select('Bid strategy', ['LOWEST_COST_WITHOUT_CAP' => 'Lowest cost', 'LOWEST_COST_WITH_BID_CAP' => 'Bid cap', 'COST_CAP' => 'Cost cap', 'LOWEST_COST_WITH_MIN_ROAS' => 'Minimum return on ad spend'])),
                'buying_type' => Field::advanced(Field::select('Buying type', ['AUCTION' => 'Auction', 'RESERVED' => 'Reserved'])),
                'special_ad_category_country' => Field::advanced(Field::list('Special category countries', 'Two-letter codes.')),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field of the Marketing API\'s campaign, like promoted_object.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $budgeted = filled($config['daily_budget'] ?? null) || filled($config['lifetime_budget'] ?? null);
        $params = [
            ...$this->jsonSetting($config, 'more_fields', 'More fields'),
            'name' => $this->requiredString($config, 'name', 'The name'),
            'objective' => $this->requiredString($config, 'objective', 'The objective'),
            'status' => $this->statusOf($config),
            'special_ad_categories' => array_map('strtoupper', $this->stringList($config, 'special_ad_categories')),
            'special_ad_category_country' => $this->stringList($config, 'special_ad_category_country') ?: null,
            ...$this->only($config, ['daily_budget', 'lifetime_budget', 'spend_cap', 'bid_strategy', 'buying_type']),
        ];

        if (! $budgeted) {
            $params['is_adset_budget_sharing_enabled'] ??= false;
        }

        $created = $this->graph($run, $config, 'POST', '/'.$this->accountPath($config).'/campaigns', $params);

        return ['id' => $created['id'] ?? null, 'status' => $params['status']];
    }
}
