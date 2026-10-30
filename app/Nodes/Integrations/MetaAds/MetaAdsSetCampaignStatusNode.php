<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsSetCampaignStatusNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_set_campaign_status';
    }

    public function name(): string
    {
        return 'Meta Ads: Set Campaign Status';
    }

    public function description(): string
    {
        return 'Activates or pauses a campaign. An active campaign spends money, as long as what it belongs to is active too.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_id' => Field::dynamic('Campaign', 'meta_ads.campaigns', null, null, ['account_id']),
                'status' => Field::select('Status', ['PAUSED' => 'Paused', 'ACTIVE' => 'Active (spends money)'], null, 'PAUSED'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'campaign_id', 'The campaign');
        $status = $this->statusOf($config);

        $this->graph($run, $config, 'POST', "/{$id}", ['status' => $status]);

        return ['id' => $id, 'status' => $status];
    }
}
