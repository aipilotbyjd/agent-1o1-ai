<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsDeleteImageNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_delete_image';
    }

    public function name(): string
    {
        return 'Meta Ads: Delete Ad Image';
    }

    public function description(): string
    {
        return 'Takes an image out of an ad account. An image that a creative uses cannot be removed.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'hash'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'hash' => Field::text('Image hash'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['deleted' => (bool) ($this->graph($run, $config, 'DELETE', '/'.$this->accountPath($config).'/adimages', ['hash' => $this->requiredString($config, 'hash', 'The hash')])['success'] ?? true)];
    }
}
