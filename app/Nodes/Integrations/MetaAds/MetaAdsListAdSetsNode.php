<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListAdSetsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_ad_sets';
    }

    public function name(): string
    {
        return 'Meta Ads: List Ad Sets';
    }

    public function description(): string
    {
        return 'Lists the ad sets of an ad account or of one campaign.';
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
                ...$this->statusFilterField(),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $parent = filled($config['campaign_id'] ?? null) ? $this->nodeId($config, 'campaign_id', 'The campaign') : $this->accountPath($config);

        return $this->listResult($this->graph($run, $config, 'GET', "/{$parent}/adsets", ['fields' => self::AD_SET_FIELDS, ...$this->statusFilter($config), ...$this->paging($config)]), 'ad_sets');
    }
}
