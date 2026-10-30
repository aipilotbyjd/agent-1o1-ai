<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsGetCreativeNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_get_creative';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Get Creative';
    }

    public function description(): string
    {
        return 'Reads one ad creative.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'creative_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'creative_id' => Field::text('Creative', 'Its id or URN.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['creative' => $this->shapeCreative($this->linkedin($run, $config, 'GET', '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account').'/creatives/'.rawurlencode($this->urn($config, 'creative_id', 'The creative', 'sponsoredCreative'))))];
    }
}
