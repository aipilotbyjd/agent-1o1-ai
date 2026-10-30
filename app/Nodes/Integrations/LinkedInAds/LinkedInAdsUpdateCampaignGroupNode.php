<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsUpdateCampaignGroupNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_update_campaign_group';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Update Campaign Group';
    }

    public function description(): string
    {
        return 'Changes the name, status, budget or dates of a campaign group. Setting it active lets its campaigns spend money.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config, 'status', 'PAUSED');
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'campaign_group_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_group_id' => Field::dynamic('Campaign group', 'linkedin_ads.campaign_groups', null, null, ['account_id']),
                'name' => Field::text('Name'),
                'total_budget' => Field::text('Total budget', 'An amount in the account\'s currency, like 5000.00.'),
                'currency' => Field::text('Currency', 'Three letters, like USD. Must match the account.'),
                'start' => Field::datetime('Start'),
                'end' => Field::datetime('End'),
                'status' => Field::select('Status', ['ACTIVE', 'PAUSED', 'ARCHIVED']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'campaign_group_id', 'The campaign group');
        $set = $this->only($config, ['name', 'status']);

        if (filled($config['total_budget'] ?? null)) {
            $set['totalBudget'] = ['amount' => (string) $config['total_budget'], 'currencyCode' => strtoupper($this->requiredString($config, 'currency', 'The currency'))];
        }

        if (filled($config['start'] ?? null) || filled($config['end'] ?? null)) {
            $set['runSchedule'] = array_filter(['start' => filled($config['start'] ?? null) ? $this->epochMs($config['start']) : null, 'end' => filled($config['end'] ?? null) ? $this->epochMs($config['end']) : null]);
        }

        $this->patch($run, $config, '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account')."/adCampaignGroups/{$id}", $set);

        return ['updated' => true, 'id' => $id];
    }
}
