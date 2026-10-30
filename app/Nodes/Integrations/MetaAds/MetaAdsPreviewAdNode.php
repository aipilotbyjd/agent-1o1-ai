<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsPreviewAdNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_preview_ad';
    }

    public function name(): string
    {
        return 'Meta Ads: Preview Ad';
    }

    public function description(): string
    {
        return 'Gets an HTML preview of how an ad looks in a placement.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ad_id', 'ad_format'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_id' => Field::dynamic('Ad', 'meta_ads.ads', null, null, ['account_id']),
                'ad_format' => Field::text('Placement', 'Like DESKTOP_FEED_STANDARD, MOBILE_FEED_STANDARD, INSTAGRAM_STANDARD or INSTAGRAM_STORY.', 'MOBILE_FEED_STANDARD'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'ad_id', 'The ad').'/previews', ['ad_format' => $this->requiredString($config, 'ad_format', 'The placement')]), 'previews');
    }
}
