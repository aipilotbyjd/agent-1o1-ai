<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListPostCommentsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_post_comments';
    }

    public function name(): string
    {
        return 'Meta Ads: List Post Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on a Page post.';
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
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->postId($config).'/comments', ['fields' => 'id,message,from,created_time,like_count', ...$this->paging($config)], $this->pageToken($run, $config, $this->nodeId($config, 'page_id', 'The Page'))), 'comments');
    }
}
