<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsSearchCampaignsNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_search_campaigns';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Search Campaigns';
    }

    public function description(): string
    {
        return 'Lists the campaigns of an ad account by status, type, group or name, newest first.';
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
                'status' => Field::select('Status', ['ACTIVE', 'PAUSED', 'ARCHIVED', 'COMPLETED', 'CANCELED', 'DRAFT', 'PENDING_DELETION', 'REMOVED']),
                'name' => Field::text('Name'),
                'type' => Field::advanced(Field::select('Type', ['TEXT_AD', 'SPONSORED_UPDATES', 'SPONSORED_INMAILS', 'DYNAMIC'])),
                'campaign_group_id' => Field::advanced(Field::dynamic('Campaign group', 'linkedin_ads.campaign_groups', null, null, ['account_id'])),
                ...$this->pageSizeField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $group = filled($config['campaign_group_id'] ?? null) ? [$this->urn($config, 'campaign_group_id', 'The campaign group', 'sponsoredCampaignGroup')] : [];
        $query = 'q=search&'.self::searchValue(['campaignGroup' => $group, 'status' => $this->stringList($config, 'status'), 'type' => $this->stringList($config, 'type'), 'name' => $this->stringList($config, 'name')]).'sortOrder=DESCENDING&'.$this->pageQuery($config);

        return $this->elements($this->linkedin($run, $config, 'GET', '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account').'/adCampaigns', $query), 'campaigns', $this->shapeCampaign(...));
    }
}
