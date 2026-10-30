<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsCreateCreativeNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_create_creative';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Create Creative';
    }

    public function description(): string
    {
        return 'Creates an ad from an existing post or other content. It starts as a draft, so nothing serves until you make it active.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config, 'intended_status', 'DRAFT');
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
                'content_reference' => Field::text('Post', 'The post to sponsor, like urn:li:ugcPost:123. Or give the content below.'),
                'content' => Field::advanced(Field::json('Content', 'LinkedIn\'s content object for other ad formats, like eventAd or textAd.')),
                'intended_status' => Field::select('Status', ['DRAFT' => 'Draft (serves nothing)', 'ACTIVE' => 'Active (can spend money)'], null, 'DRAFT'),
                'leadgen_call_to_action' => Field::advanced(Field::json('Lead form call to action', 'Needed on lead generation campaigns: buttonLabel and destination.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $account = $this->numericId($config, 'account_id', 'The ad account');
        $content = $this->jsonSetting($config, 'content', 'The content') ?: ['reference' => $this->anyUrn($config, 'content_reference', 'The post')];
        $body = array_filter(['campaign' => $this->urn($config, 'campaign_id', 'The campaign', 'sponsoredCampaign'), 'name' => $config['name'] ?? null, 'content' => $content, 'intendedStatus' => strtoupper((string) ($config['intended_status'] ?? 'DRAFT')), 'leadgenCallToAction' => $this->jsonSetting($config, 'leadgen_call_to_action', 'The call to action') ?: null]);

        return ['created' => true, 'id' => $this->create($run, $config, "/adAccounts/{$account}/creatives", $body), 'intended_status' => $body['intendedStatus']];
    }
}
