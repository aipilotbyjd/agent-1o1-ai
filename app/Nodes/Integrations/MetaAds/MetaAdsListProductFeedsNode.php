<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListProductFeedsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_product_feeds';
    }

    public function name(): string
    {
        return 'Meta Ads: List Product Feeds';
    }

    public function description(): string
    {
        return 'Lists the data feeds of a catalog with their status.';
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
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'catalog_id', 'The catalog').'/product_feeds', ['fields' => 'id,name,product_count,schedule,latest_upload,created_time', ...$this->paging($config)]), 'feeds');
    }
}
