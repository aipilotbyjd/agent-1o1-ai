<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsDeleteCreativeNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_delete_creative';
    }

    public function name(): string
    {
        return 'Meta Ads: Delete Creative';
    }

    public function description(): string
    {
        return 'Deletes a creative from the library. This cannot be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['creative_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'creative_id' => Field::dynamic('Creative', 'meta_ads.creatives', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'creative_id', 'The creative');

        return ['id' => $id, 'deleted' => (bool) ($this->graph($run, $config, 'DELETE', "/{$id}")['success'] ?? true)];
    }
}
