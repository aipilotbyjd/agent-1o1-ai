<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MetaAdsListPagesNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_pages';
    }

    public function name(): string
    {
        return 'Meta Ads: List Pages';
    }

    public function description(): string
    {
        return 'Lists the Facebook Pages the connected member manages.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/me/accounts', ['fields' => 'id,name,category,fan_count,followers_count,link', ...$this->paging($config)]), 'pages');
    }
}
