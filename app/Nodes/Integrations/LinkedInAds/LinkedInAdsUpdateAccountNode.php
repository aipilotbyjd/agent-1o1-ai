<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsUpdateAccountNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_update_account';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Update Ad Account';
    }

    public function description(): string
    {
        return 'Changes the name of an ad account or what it notifies about.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'name' => Field::text('Name'),
                'notifiedOnCreativeApproval' => Field::advanced(Field::boolean('Notify when an ad is approved')),
                'notifiedOnCreativeRejection' => Field::advanced(Field::boolean('Notify when an ad is rejected')),
                'notifiedOnEndOfCampaign' => Field::advanced(Field::boolean('Notify when a campaign ends')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'account_id', 'The ad account');

        $this->patch($run, $config, "/adAccounts/{$id}", $this->only($config, ['name', 'notifiedOnCreativeApproval', 'notifiedOnCreativeRejection', 'notifiedOnEndOfCampaign']));

        return ['updated' => true, 'id' => $id];
    }
}
