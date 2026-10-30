<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListCatalogsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_catalogs';
    }

    public function name(): string
    {
        return 'Meta Ads: List Catalogs';
    }

    public function description(): string
    {
        return 'Lists the product catalogs of a Business Manager.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['business_id'],
            'properties' => [
                ...$this->credentialFields(),
                'business_id' => Field::dynamic('Business', 'meta_ads.businesses'),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'business_id', 'The business').'/owned_product_catalogs', ['fields' => 'id,name,vertical,product_count,feed_count', ...$this->paging($config)]), 'catalogs');
    }
}
