<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsRemoveAccountUserNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_remove_account_user';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Remove Account User';
    }

    public function description(): string
    {
        return 'Takes away a member\'s access to an ad account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'user'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'user' => Field::text('Member', 'Their URN, like urn:li:person:abc.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->linkedin($run, $config, 'DELETE', '/adAccountUsers/(account:'.rawurlencode($this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount')).',user:'.rawurlencode($this->anyUrn($config, 'user', 'The member')).')');

        return ['removed' => true];
    }
}
