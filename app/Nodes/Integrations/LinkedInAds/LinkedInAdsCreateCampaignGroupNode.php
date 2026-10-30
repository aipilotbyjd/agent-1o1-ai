<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsCreateCampaignGroupNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_create_campaign_group';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Create Campaign Group';
    }

    public function description(): string
    {
        return 'Creates a campaign group to hold campaigns. It starts as a draft, and only as a live group can its campaigns spend money.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config, 'status', 'DRAFT');
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
                'total_budget' => Field::text('Total budget', 'An amount in the account\'s currency, like 5000.00.'),
                'currency' => Field::text('Currency', 'Three letters, like USD. Must match the account.'),
                'start' => Field::datetime('Start'),
                'end' => Field::datetime('End'),
                'status' => Field::select('Status', ['DRAFT' => 'Draft (spends nothing)', 'ACTIVE' => 'Active (can spend money)'], null, 'DRAFT'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $account = $this->numericId($config, 'account_id', 'The ad account');
        $body = ['account' => "urn:li:sponsoredAccount:{$account}", 'name' => $this->requiredString($config, 'name', 'The name'), 'status' => strtoupper((string) ($config['status'] ?? 'DRAFT')), 'runSchedule' => array_filter(['start' => $this->epochMs($config['start'] ?? now()->toIso8601String()), 'end' => filled($config['end'] ?? null) ? $this->epochMs($config['end']) : null])];

        if (filled($config['total_budget'] ?? null)) {
            $body['totalBudget'] = ['amount' => (string) $config['total_budget'], 'currencyCode' => strtoupper($this->requiredString($config, 'currency', 'The currency'))];
        }

        return ['created' => true, 'id' => $this->create($run, $config, "/adAccounts/{$account}/adCampaignGroups", $body), 'status' => $body['status']];
    }
}
