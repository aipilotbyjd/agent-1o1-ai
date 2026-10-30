<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class LinkedInAdsListConversionsNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_conversions';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List Conversion Rules';
    }

    public function description(): string
    {
        return 'Lists the conversion rules of an ad account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->indexedElements($this->linkedin($run, $config, 'GET', '/conversions', 'q=account&account='.rawurlencode($this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount'))), 'conversions');
    }
}
