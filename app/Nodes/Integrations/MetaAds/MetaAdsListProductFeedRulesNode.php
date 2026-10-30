<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListProductFeedRulesNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_product_feed_rules';
    }

    public function name(): string
    {
        return 'Meta Ads: List Product Feed Rules';
    }

    public function description(): string
    {
        return 'Lists the rules applied to a data feed.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['feed_id'],
            'properties' => [
                ...$this->credentialFields(),
                'feed_id' => Field::text('Feed id'),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'feed_id', 'The feed').'/rules', $this->paging($config)), 'rules');
    }
}
