<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MetaAdsGetAccountNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_get_account';
    }

    public function name(): string
    {
        return 'Meta Ads: Get Ad Account';
    }

    public function description(): string
    {
        return 'Reads one ad account: its name, status, currency, spend and limit.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['account' => $this->graph($run, $config, 'GET', '/'.$this->accountPath($config), ['fields' => self::ACCOUNT_FIELDS])];
    }
}
