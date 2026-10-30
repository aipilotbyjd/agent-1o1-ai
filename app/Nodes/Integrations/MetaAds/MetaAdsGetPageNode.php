<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetPageNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_page';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Page';
    }

    public function description(): string
    {
        return 'Reads a Page\'s details and engagement, like followers and category.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page_id'],
            'properties' => [
                ...$this->credentialFields(),
                'page_id' => Field::dynamic('Page', 'meta_ads.pages'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['page' => $this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'page_id', 'The Page'), ['fields' => 'id,name,about,category,fan_count,followers_count,link,website,verification_status'])];
    }
}
