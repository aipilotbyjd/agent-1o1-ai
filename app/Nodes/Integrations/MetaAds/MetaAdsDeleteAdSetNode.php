<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsDeleteAdSetNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_delete_ad_set';
    }

    public function name(): string
    {
        return 'Meta Ads: Delete Ad Set';
    }

    public function description(): string
    {
        return 'Deletes a ad set, and it stops running. This cannot be undone: archive or pause it to keep the data.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        return ['id' => $id, 'deleted' => (bool) ($this->graph($run, $config, 'DELETE', "/{$id}")['success'] ?? true)];
    }
}
