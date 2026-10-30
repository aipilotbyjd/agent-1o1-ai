<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListPagePostsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_page_posts';
    }

    public function name(): string
    {
        return 'Meta Ads: List Page Posts';
    }

    public function description(): string
    {
        return 'Lists the posts published on a Page, newest first.';
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
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->nodeId($config, 'page_id', 'The Page');

        return $this->listResult($this->graph($run, $config, 'GET', "/{$page}/posts", ['fields' => 'id,message,created_time,permalink_url,shares', ...$this->paging($config)], $this->pageToken($run, $config, $page)), 'posts');
    }
}
