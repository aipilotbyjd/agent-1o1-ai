<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetProductNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_product';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Catalog Product';
    }

    public function description(): string
    {
        return 'Reads one catalog product.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['product_id'],
            'properties' => [
                ...$this->credentialFields(),
                'product_id' => Field::text('Product id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['product' => $this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'product_id', 'The product'), ['fields' => 'id,retailer_id,name,description,price,currency,availability,condition,brand,image_url,url,review_status'])];
    }
}
