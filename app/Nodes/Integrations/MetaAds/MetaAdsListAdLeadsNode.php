<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsListAdLeadsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_ad_leads';
    }

    public function name(): string
    {
        return 'Meta Ads: List Ad Leads';
    }

    public function description(): string
    {
        return 'Reads the leads that came from one ad.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ad_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_id' => Field::dynamic('Ad', 'meta_ads.ads', null, null, ['account_id']),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->graph($run, $config, 'GET', '/'.$this->nodeId($config, 'ad_id', 'The ad').'/leads', ['fields' => self::LEAD_FIELDS, ...$this->paging($config)]), 'leads', $this->shapeLead(...));
    }
}
