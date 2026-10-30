<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MetaAdsListBusinessesNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_businesses';
    }

    public function name(): string
    {
        return 'Meta Ads: List Businesses';
    }

    public function description(): string
    {
        return 'Lists the Business Managers of the connected member.';
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
        return $this->listResult($this->graph($run, $config, 'GET', '/me/businesses', ['fields' => 'id,name,verification_status', ...$this->paging($config)]), 'businesses');
    }
}
