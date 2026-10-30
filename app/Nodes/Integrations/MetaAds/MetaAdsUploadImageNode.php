<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsUploadImageNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_upload_image';
    }

    public function name(): string
    {
        return 'Meta Ads: Upload Ad Image';
    }

    public function description(): string
    {
        return 'Adds an image to an ad account from its base64 text, and returns the hash creatives use.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'bytes'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'bytes' => Field::textarea('Image', 'The file\'s content as base64 text.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $images = (array) ($this->graph($run, $config, 'POST', '/'.$this->accountPath($config).'/adimages', ['bytes' => $this->requiredString($config, 'bytes', 'The image')])['images'] ?? []);
        $image = (array) (reset($images) ?: []);

        return ['hash' => $image['hash'] ?? null, 'url' => $image['url'] ?? null, 'image' => $image];
    }
}
