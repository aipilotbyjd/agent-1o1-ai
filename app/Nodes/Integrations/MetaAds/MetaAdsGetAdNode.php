<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetAdNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_ad';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Ad';
    }

    public function description(): string
    {
        return 'Reads one ad.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ad_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_id' => Field::dynamic('Ad', 'meta_ads.ads', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['ad' => $this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'ad_id', 'The ad'), ['fields' => self::AD_FIELDS])];
    }
}
