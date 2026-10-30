<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MetaAdsListAccountsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_list_accounts';
    }

    public function name(): string
    {
        return 'Meta Ads: List Ad Accounts';
    }

    public function description(): string
    {
        return 'Lists the ad accounts the connected member can use, and checks the connection works.';
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
        return $this->listResult($this->graph($run, $config, 'GET', '/me/adaccounts', ['fields' => self::ACCOUNT_FIELDS, ...$this->paging($config)]), 'accounts');
    }
}
