<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetCampaignNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_campaign';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Campaign';
    }

    public function description(): string
    {
        return 'Reads one campaign.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['campaign' => $this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'campaign_id', 'The campaign'), ['fields' => self::CAMPAIGN_FIELDS])];
    }
}
