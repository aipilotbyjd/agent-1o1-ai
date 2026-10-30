<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MetaAdsListImagesNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_images';
    }

    public function name(): string
    {
        return 'Meta Ads: List Ad Images';
    }

    public function description(): string
    {
        return 'Lists the images in an ad account, with the hashes creatives use.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->accountPath($config).'/adimages', ['fields' => 'id,hash,name,url,width,height,status', ...$this->paging($config)]), 'images');
    }
}
