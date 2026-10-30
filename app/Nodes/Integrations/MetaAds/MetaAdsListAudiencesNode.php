<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MetaAdsListAudiencesNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_audiences';
    }

    public function name(): string
    {
        return 'Meta Ads: List Custom Audiences';
    }

    public function description(): string
    {
        return 'Lists the custom audiences of an ad account.';
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
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->accountPath($config).'/customaudiences', ['fields' => self::AUDIENCE_FIELDS, ...$this->paging($config)]), 'audiences');
    }
}
