<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsDeleteAdNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_delete_ad';
    }

    public function name(): string
    {
        return 'Meta Ads: Delete Ad';
    }

    public function description(): string
    {
        return 'Deletes a ad, and it stops running. This cannot be undone: archive or pause it to keep the data.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'ad_id', 'The ad');

        return ['id' => $id, 'deleted' => (bool) ($this->graph($run, $config, 'DELETE', "/{$id}")['success'] ?? true)];
    }
}
