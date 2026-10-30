<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsArchiveAdSetNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_archive_ad_set';
    }

    public function name(): string
    {
        return 'Meta Ads: Archive Ad Set';
    }

    public function description(): string
    {
        return 'Archives a ad set: it stops running and keeps its data, but can no longer be changed except to be deleted.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ad_set_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ad_set_id' => Field::dynamic('Ad set', 'meta_ads.ad_sets', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'ad_set_id', 'The ad set');

        $this->graph($run, $config, 'POST', "/{$id}", ['status' => 'ARCHIVED']);

        return ['id' => $id, 'status' => 'ARCHIVED'];
    }
}
