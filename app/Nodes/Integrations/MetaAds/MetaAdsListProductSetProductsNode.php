<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListProductSetProductsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_product_set_products';
    }

    public function name(): string
    {
        return 'Meta Ads: List Product Set Products';
    }

    public function description(): string
    {
        return 'Lists the products in a product set.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['product_set_id'],
            'properties' => [
                ...$this->credentialFields(),
                'product_set_id' => Field::text('Product set id'),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'product_set_id', 'The product set').'/products', ['fields' => 'id,retailer_id,name,price,availability,image_url,url', ...$this->paging($config)]), 'products');
    }
}
