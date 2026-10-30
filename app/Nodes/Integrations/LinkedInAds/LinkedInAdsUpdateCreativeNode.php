<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsUpdateCreativeNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_update_creative';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Update Creative';
    }

    public function description(): string
    {
        return 'Renames a creative or changes its status. An active creative in a live campaign spends money.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config, 'intended_status');
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
                'name' => Field::text('Name'),
                'intended_status' => Field::select('Status', ['PAUSED' => 'Paused', 'ACTIVE' => 'Active (can spend money)', 'ARCHIVED' => 'Archived', 'CANCELED' => 'Canceled']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $set = array_filter(['name' => $config['name'] ?? null, 'intendedStatus' => filled($config['intended_status'] ?? null) ? strtoupper((string) $config['intended_status']) : null]);

        $this->patch($run, $config, '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account').'/creatives/'.rawurlencode($this->urn($config, 'creative_id', 'The creative', 'sponsoredCreative')), $set);

        return ['updated' => true, 'id' => $this->urn($config, 'creative_id', 'The creative', 'sponsoredCreative')];
    }
}
