<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsSetCampaignStatusNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_set_campaign_status';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Set Campaign Status';
    }

    public function description(): string
    {
        return 'Activates, pauses or archives a campaign. Active campaigns spend money.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'campaign_id', 'status'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_id' => Field::dynamic('Campaign', 'linkedin_ads.campaigns', null, null, ['account_id']),
                'status' => Field::select('Status', ['PAUSED' => 'Paused', 'ACTIVE' => 'Active (spends money)', 'ARCHIVED' => 'Archived', 'CANCELED' => 'Canceled'], null, 'PAUSED'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'campaign_id', 'The campaign');
        $status = strtoupper($this->requiredString($config, 'status', 'The status'));

        $this->patch($run, $config, '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account')."/adCampaigns/{$id}", ['status' => $status]);

        return ['updated' => true, 'id' => $id, 'status' => $status];
    }
}
