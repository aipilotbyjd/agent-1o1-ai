<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetPixelNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_pixel';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Pixel';
    }

    public function description(): string
    {
        return 'Reads one pixel or dataset.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pixel_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'pixel_id' => Field::dynamic('Pixel', 'meta_ads.pixels', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['pixel' => $this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'pixel_id', 'The pixel'), ['fields' => 'id,name,code,creation_time,last_fired_time,is_unavailable,enable_automatic_matching,automatic_matching_fields,data_use_setting,first_party_cookie_status,owner_business'])];
    }
}
