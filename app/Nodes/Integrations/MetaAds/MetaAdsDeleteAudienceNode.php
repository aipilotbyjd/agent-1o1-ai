<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsDeleteAudienceNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_delete_audience';
    }

    public function name(): string
    {
        return 'Meta Ads: Delete Custom Audience';
    }

    public function description(): string
    {
        return 'Deletes a custom audience, and ads that use it stop running. This cannot be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'audience_id' => Field::dynamic('Audience', 'meta_ads.audiences', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'audience_id', 'The audience');

        return ['id' => $id, 'deleted' => (bool) ($this->graph($run, $config, 'DELETE', "/{$id}")['success'] ?? true)];
    }
}
