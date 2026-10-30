<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetCatalogNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_catalog';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Catalog';
    }

    public function description(): string
    {
        return 'Reads one product catalog.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['catalog_id'],
            'properties' => [
                ...$this->credentialFields(),
                'business_id' => Field::dynamic('Business', 'meta_ads.businesses'),
                'catalog_id' => Field::dynamic('Catalog', 'meta_ads.catalogs', null, null, ['business_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['catalog' => $this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'catalog_id', 'The catalog'), ['fields' => 'id,name,vertical,product_count,feed_count,business,is_catalog_segment'])];
    }
}
