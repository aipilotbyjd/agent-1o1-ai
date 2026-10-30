<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MetaAdsListCampaignsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_campaigns';
    }

    public function name(): string
    {
        return 'Meta Ads: List Campaigns';
    }

    public function description(): string
    {
        return 'Lists the campaigns of an ad account, optionally only those in some statuses.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                ...$this->statusFilterField(),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->accountPath($config).'/campaigns', ['fields' => self::CAMPAIGN_FIELDS, ...$this->statusFilter($config), ...$this->paging($config)]), 'campaigns');
    }
}
