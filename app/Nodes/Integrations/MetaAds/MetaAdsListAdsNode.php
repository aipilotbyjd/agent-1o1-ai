<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListAdsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_ads';
    }

    public function name(): string
    {
        return 'Meta Ads: List Ads';
    }

    public function description(): string
    {
        return 'Lists the ads of an ad account, a campaign or an ad set.';
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
                'campaign_id' => Field::dynamic('Only this campaign', 'meta_ads.campaigns', null, null, ['account_id']),
                'ad_set_id' => Field::dynamic('Only this ad set', 'meta_ads.ad_sets', null, null, ['account_id']),
                ...$this->statusFilterField(),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $parent = filled($config['ad_set_id'] ?? null) ? $this->nodeId($config, 'ad_set_id', 'The ad set') : (filled($config['campaign_id'] ?? null) ? $this->nodeId($config, 'campaign_id', 'The campaign') : $this->accountPath($config));

        return $this->listResult($this->graph($run, $config, 'GET', "/{$parent}/ads", ['fields' => self::AD_FIELDS, ...$this->statusFilter($config), ...$this->paging($config)]), 'ads');
    }
}
