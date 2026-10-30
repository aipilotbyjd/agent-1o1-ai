<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsUpdateCampaignNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_update_campaign';
    }

    public function name(): string
    {
        return 'Meta Ads: Update Campaign';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on a campaign. A higher budget, or making it active, spends more money.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->changeEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_id' => Field::dynamic('Campaign', 'meta_ads.campaigns', null, null, ['account_id']),
                'name' => Field::text('Name'),
                'status' => Field::select('Status', ['PAUSED' => 'Paused', 'ACTIVE' => 'Active (spends money)', 'ARCHIVED' => 'Archived']),
                'daily_budget' => Field::advanced(Field::integer('Daily budget', 'In the smallest unit of the currency.')),
                'lifetime_budget' => Field::advanced(Field::integer('Lifetime budget', 'In the smallest unit of the currency.')),
                'spend_cap' => Field::advanced(Field::integer('Spending limit', 'In the smallest unit of the currency.')),
                'bid_strategy' => Field::advanced(Field::select('Bid strategy', ['LOWEST_COST_WITHOUT_CAP' => 'Lowest cost', 'LOWEST_COST_WITH_BID_CAP' => 'Bid cap', 'COST_CAP' => 'Cost cap', 'LOWEST_COST_WITH_MIN_ROAS' => 'Minimum return on ad spend'])),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field to change.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'campaign_id', 'The campaign');
        $params = [...$this->jsonSetting($config, 'more_fields', 'More fields'), ...$this->only($config, ['name', 'status', 'daily_budget', 'lifetime_budget', 'spend_cap', 'bid_strategy'])];

        if ($params === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        return ['id' => $id, 'updated' => (bool) ($this->graph($run, $config, 'POST', "/{$id}", $params)['success'] ?? true)];
    }
}
