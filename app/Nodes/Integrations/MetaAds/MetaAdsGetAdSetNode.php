<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetAdSetNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_ad_set';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Ad Set';
    }

    public function description(): string
    {
        return 'Reads one ad set.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ad_set_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_set_id' => Field::dynamic('Ad set', 'meta_ads.ad_sets', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['ad_set' => $this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'ad_set_id', 'The ad set'), ['fields' => self::AD_SET_FIELDS])];
    }
}
