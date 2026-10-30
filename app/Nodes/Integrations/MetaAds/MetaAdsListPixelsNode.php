<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MetaAdsListPixelsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_pixels';
    }

    public function name(): string
    {
        return 'Meta Ads: List Pixels';
    }

    public function description(): string
    {
        return 'Lists the pixels and datasets of an ad account.';
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
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->accountPath($config).'/adspixels', ['fields' => 'id,name,last_fired_time,creation_time,is_unavailable', ...$this->paging($config)]), 'pixels');
    }
}
