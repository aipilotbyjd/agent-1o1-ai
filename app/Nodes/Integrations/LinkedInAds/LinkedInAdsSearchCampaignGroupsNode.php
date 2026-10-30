<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsSearchCampaignGroupsNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_search_campaign_groups';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Search Campaign Groups';
    }

    public function description(): string
    {
        return 'Lists the campaign groups of an ad account by status or name, newest first.';
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
                'status' => Field::select('Status', ['ACTIVE', 'ARCHIVED', 'CANCELED', 'DRAFT', 'PAUSED', 'PENDING_DELETION', 'REMOVED']),
                'name' => Field::text('Name'),
                ...$this->pageSizeField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = 'q=search&'.self::searchValue(['status' => $this->stringList($config, 'status'), 'name' => $this->stringList($config, 'name')]).'sortOrder=DESCENDING&'.$this->pageQuery($config);

        return $this->elements($this->linkedin($run, $config, 'GET', '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account').'/adCampaignGroups', $query), 'campaign_groups', $this->shapeCampaignGroup(...));
    }
}
