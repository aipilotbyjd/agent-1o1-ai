<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsSearchCreativesNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_search_creatives';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Search Creatives';
    }

    public function description(): string
    {
        return 'Lists the creatives of an ad account by campaign or status, newest first.';
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
                'campaign_id' => Field::dynamic('Campaign', 'linkedin_ads.campaigns', null, null, ['account_id']),
                'intended_status' => Field::select('Status', ['ACTIVE', 'ARCHIVED', 'CANCELED', 'DRAFT', 'PAUSED', 'PENDING_DELETION', 'REMOVED']),
                ...$this->pageSizeField(25, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = 'q=criteria&sortOrder=DESCENDING&'.$this->pageQuery($config, 25, 100);

        if (filled($config['campaign_id'] ?? null)) {
            $query .= '&campaigns='.self::restliList([$this->urn($config, 'campaign_id', 'The campaign', 'sponsoredCampaign')]);
        }

        if (filled($config['intended_status'] ?? null)) {
            $query .= '&intendedStatuses='.self::restliList($this->stringList($config, 'intended_status'));
        }

        return $this->elements($this->linkedin($run, $config, 'GET', '/adAccounts/'.$this->numericId($config, 'account_id', 'The ad account').'/creatives', $query), 'creatives', $this->shapeCreative(...));
    }
}
