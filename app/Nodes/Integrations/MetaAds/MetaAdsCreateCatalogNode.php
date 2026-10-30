<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsCreateCatalogNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_create_catalog';
    }

    public function name(): string
    {
        return 'Meta Ads: Create Catalog';
    }

    public function description(): string
    {
        return 'Creates a product catalog in a Business Manager.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['business_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'business_id' => Field::dynamic('Business', 'meta_ads.businesses'),
                'name' => Field::text('Name'),
                'vertical' => Field::advanced(Field::select('Kind', ['commerce', 'hotels', 'flights', 'destinations', 'home_listings', 'vehicles'], null, 'commerce')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->graph($run, $config, 'POST', '/'.$this->nodeId($config, 'business_id', 'The business').'/owned_product_catalogs', ['name' => $this->requiredString($config, 'name', 'The name'), ...$this->only($config, ['vertical'])]);
    }
}
