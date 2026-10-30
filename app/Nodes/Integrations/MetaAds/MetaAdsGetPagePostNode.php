<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsGetPagePostNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_page_post';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Page Post';
    }

    public function description(): string
    {
        return 'Reads one Page post by its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page_id', 'post_id'],
            'properties' => [
                ...$this->credentialFields(),
                'page_id' => Field::dynamic('Page', 'meta_ads.pages'),
                'post_id' => Field::text('Post id', 'As page id_post id.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['post' => $this->graph($run, $config, 'GET', '/'.$this->postId($config), ['fields' => 'id,message,created_time,permalink_url,shares'], $this->pageToken($run, $config, $this->nodeId($config, 'page_id', 'The Page')))];
    }
}
