<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsDeleteCreativeNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_delete_creative';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Delete Creative';
    }

    public function description(): string
    {
        return 'Deletes a draft creative at once, and marks one that has been live for deletion. This cannot be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        return ['result' => $this->remove($run, $config, '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account').'/creatives/'.rawurlencode($this->urn($config, 'creative_id', 'The creative', 'sponsoredCreative')), 'intendedStatus')];
    }
}
