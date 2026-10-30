<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetDeliveryEstimateNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_delivery_estimate';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Delivery Estimate';
    }

    public function description(): string
    {
        return 'Estimates how many people an ad set can reach per day, for its current audience or for one you give.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_set_id' => Field::dynamic('Ad set', 'meta_ads.ad_sets', 'Or leave empty and give the account, goal and targeting.', null, ['account_id']),
                'optimization_goal' => Field::text('Optimize for', 'Like LINK_CLICKS or REACH.'),
                'targeting_spec' => Field::json('Targeting', 'The Marketing API\'s targeting spec.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        if (filled($config['ad_set_id'] ?? null)) {
            return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'ad_set_id', 'The ad set').'/delivery_estimate', ['targeting_spec' => $this->jsonSetting($config, 'targeting_spec', 'The targeting') ?: null, 'optimization_goal' => $config['optimization_goal'] ?? null]), 'estimates');
        }

        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->accountPath($config).'/delivery_estimate', ['targeting_spec' => $this->jsonSetting($config, 'targeting_spec', 'The targeting', true), 'optimization_goal' => $this->requiredString($config, 'optimization_goal', 'The goal')]), 'estimates');
    }
}
