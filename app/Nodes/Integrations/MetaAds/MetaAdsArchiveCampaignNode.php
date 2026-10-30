<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsArchiveCampaignNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_archive_campaign';
    }

    public function name(): string
    {
        return 'Meta Ads: Archive Campaign';
    }

    public function description(): string
    {
        return 'Archives a campaign: it stops running and keeps its data, but can no longer be changed except to be deleted.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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

        $this->graph($run, $config, 'POST', "/{$id}", ['status' => 'ARCHIVED']);

        return ['id' => $id, 'status' => 'ARCHIVED'];
    }
}
