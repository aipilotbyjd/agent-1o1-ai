<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetAudienceNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_audience';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Custom Audience';
    }

    public function description(): string
    {
        return 'Reads one custom audience with its size and status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'audience_id' => Field::dynamic('Audience', 'meta_ads.audiences', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['audience' => $this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'audience_id', 'The audience'), ['fields' => self::AUDIENCE_FIELDS])];
    }
}
