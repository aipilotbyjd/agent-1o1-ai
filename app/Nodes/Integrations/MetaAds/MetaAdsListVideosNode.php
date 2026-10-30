<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MetaAdsListVideosNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_videos';
    }

    public function name(): string
    {
        return 'Meta Ads: List Ad Videos';
    }

    public function description(): string
    {
        return 'Lists the videos in an ad account.';
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
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->accountPath($config).'/advideos', ['fields' => 'id,title,description,length,source,picture,created_time', ...$this->paging($config)]), 'videos');
    }
}
