<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetPixelStatsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_pixel_stats';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Pixel Stats';
    }

    public function description(): string
    {
        return 'Reads the event counts of a pixel or dataset.';
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
                'aggregation' => Field::select('Group by', ['event', 'event_source', 'device_type', 'host', 'url', 'pixel_fire', 'browser_type'], null, 'event'),
                'start_time' => Field::advanced(Field::datetime('From')),
                'end_time' => Field::advanced(Field::datetime('To')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'pixel_id', 'The pixel').'/stats', $this->only($config, ['aggregation', 'start_time', 'end_time'])), 'stats');
    }
}
