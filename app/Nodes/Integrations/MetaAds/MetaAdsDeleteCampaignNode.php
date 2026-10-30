<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsDeleteCampaignNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_delete_campaign';
    }

    public function name(): string
    {
        return 'Meta Ads: Delete Campaign';
    }

    public function description(): string
    {
        return 'Deletes a campaign, and it stops running. This cannot be undone: archive or pause it to keep the data.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_id' => Field::dynamic('Campaign', 'meta_ads.campaigns', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'campaign_id', 'The campaign');

        return ['id' => $id, 'deleted' => (bool) ($this->graph($run, $config, 'DELETE', "/{$id}")['success'] ?? true)];
    }
}
