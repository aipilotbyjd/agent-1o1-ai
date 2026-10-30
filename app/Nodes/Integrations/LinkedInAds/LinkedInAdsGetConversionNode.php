<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsGetConversionNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_get_conversion';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Get Conversion Rule';
    }

    public function description(): string
    {
        return 'Reads one conversion rule.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'conversion_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'conversion_id' => Field::dynamic('Conversion rule', 'linkedin_ads.conversions', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->linkedin($run, $config, 'GET', '/conversions/'.$this->numericId($config, 'conversion_id', 'The conversion rule'), 'account='.rawurlencode($this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount')));
    }
}
