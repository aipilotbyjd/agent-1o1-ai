<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetCatalogDiagnosticsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_catalog_diagnostics';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Catalog Diagnostics';
    }

    public function description(): string
    {
        return 'Reads the errors and warnings of a product catalog.';
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
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'catalog_id', 'The catalog').'/diagnostics'), 'diagnostics');
    }
}
