<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsUpdateCampaignNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_update_campaign';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Update Campaign';
    }

    public function description(): string
    {
        return 'Changes the fields you fill in on a campaign. A higher budget or bid on a live campaign spends more money.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->campaignChangeEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'campaign_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_id' => Field::dynamic('Campaign', 'linkedin_ads.campaigns', null, null, ['account_id']),
                'name' => Field::text('Name'),
                'daily_budget' => Field::text('Daily budget'),
                'total_budget' => Field::text('Total budget'),
                'unit_cost' => Field::text('Bid'),
                'currency' => Field::text('Currency', 'Three letters, like USD. Needed with an amount.'),
                'targeting_criteria' => Field::advanced(Field::json('Targeting', 'LinkedIn\'s targetingCriteria object.')),
                'start' => Field::advanced(Field::datetime('Start')),
                'end' => Field::advanced(Field::datetime('End')),
                'offsite_delivery_enabled' => Field::advanced(Field::boolean('Serve on the LinkedIn Audience Network')),
                'audience_expansion_enabled' => Field::advanced(Field::boolean('Expand the audience')),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field to change. Use Set Campaign Status for the status.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'campaign_id', 'The campaign');
        $set = [...$this->jsonSetting($config, 'more_fields', 'More fields'), ...$this->only($config, ['name']), ...$this->campaignExtras($config)];

        if (filled($config['targeting_criteria'] ?? null)) {
            $set['targetingCriteria'] = $this->jsonSetting($config, 'targeting_criteria', 'The targeting', true);
        }

        if (filled($config['start'] ?? null) || filled($config['end'] ?? null)) {
            $set['runSchedule'] = array_filter(['start' => filled($config['start'] ?? null) ? $this->epochMs($config['start']) : null, 'end' => filled($config['end'] ?? null) ? $this->epochMs($config['end']) : null]);
        }

        $this->patch($run, $config, '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account')."/adCampaigns/{$id}", $set);

        return ['updated' => true, 'id' => $id];
    }

    /**
     * Budgets and bids can raise what a live campaign spends.
     *
     * @param  array<string, mixed>  $config
     */
    private function campaignChangeEffect(array $config): ActionEffect
    {
        return $this->only($config, ['daily_budget', 'total_budget', 'unit_cost', 'more_fields']) === [] ? ActionEffect::Write : ActionEffect::External;
    }
}
