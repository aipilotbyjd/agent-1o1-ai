<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetCreativeNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_creative';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Creative';
    }

    public function description(): string
    {
        return 'Reads one creative.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['creative_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'creative_id' => Field::dynamic('Creative', 'meta_ads.creatives', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['creative' => $this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'creative_id', 'The creative'), ['fields' => self::CREATIVE_FIELDS])];
    }
}
