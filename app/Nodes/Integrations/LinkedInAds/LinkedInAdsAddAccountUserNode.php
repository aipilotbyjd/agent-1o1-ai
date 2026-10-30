<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsAddAccountUserNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_add_account_user';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Add Account User';
    }

    public function description(): string
    {
        return 'Gives a member access to an ad account, or changes the role of one who has it. A billing admin or an account manager can spend the account\'s budget.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'user', 'role'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'user' => Field::text('Member', 'Their URN, like urn:li:person:abc.'),
                'role' => Field::select('Role', ['ACCOUNT_BILLING_ADMIN' => 'Billing admin', 'ACCOUNT_MANAGER' => 'Account manager', 'CAMPAIGN_MANAGER' => 'Campaign manager', 'CREATIVE_MANAGER' => 'Creative manager', 'VIEWER' => 'Viewer'], null, 'VIEWER'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $account = $this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount');
        $user = $this->anyUrn($config, 'user', 'The member');

        $this->linkedin($run, $config, 'PUT', '/adAccountUsers/(account:'.rawurlencode($this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount')).',user:'.rawurlencode($this->anyUrn($config, 'user', 'The member')).')', '', ['account' => $account, 'user' => $user, 'role' => $this->requiredString($config, 'role', 'The role')]);

        return ['added' => true, 'account' => $account, 'user' => $user];
    }
}
