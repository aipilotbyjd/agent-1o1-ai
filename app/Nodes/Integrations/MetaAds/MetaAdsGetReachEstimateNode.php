<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetReachEstimateNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_reach_estimate';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Reach Estimate';
    }

    public function description(): string
    {
        return 'Estimates how large an audience is.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'targeting_spec'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'targeting_spec' => Field::json('Targeting', 'The Marketing API\'s targeting spec.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['estimate' => $this->graph($run, $config, 'GET', '/'.$this->accountPath($config).'/reachestimate', ['targeting_spec' => $this->jsonSetting($config, 'targeting_spec', 'The targeting', true)])['data'] ?? null];
    }
}
