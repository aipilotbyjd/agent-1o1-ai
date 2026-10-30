<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MetaAdsListCreativesNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_creatives';
    }

    public function name(): string
    {
        return 'Meta Ads: List Creatives';
    }

    public function description(): string
    {
        return 'Lists the creatives in an ad account\'s library.';
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
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->accountPath($config).'/adcreatives', ['fields' => self::CREATIVE_FIELDS, ...$this->paging($config)]), 'creatives');
    }
}
