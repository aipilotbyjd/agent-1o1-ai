<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsCreatePixelNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_create_pixel';
    }

    public function name(): string
    {
        return 'Meta Ads: Create Pixel';
    }

    public function description(): string
    {
        return 'Creates a pixel on an ad account. Meta allows few per account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'name' => Field::text('Name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->graph($run, $config, 'POST', '/'.$this->accountPath($config).'/adspixels', ['name' => $this->requiredString($config, 'name', 'The name')]);
    }
}
