<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListBusinessPagesNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_business_pages';
    }

    public function name(): string
    {
        return 'Meta Ads: List Business Pages';
    }

    public function description(): string
    {
        return 'Lists the Facebook Pages that belong to a Business Manager.';
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
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'business_id', 'The business').'/owned_pages', ['fields' => 'id,name,category,fan_count,link', ...$this->paging($config)]), 'pages');
    }
}
