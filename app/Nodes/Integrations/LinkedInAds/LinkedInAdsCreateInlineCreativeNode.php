<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsCreateInlineCreativeNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_create_inline_creative';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Create Creative with New Post';
    }

    public function description(): string
    {
        return 'Creates a new sponsored post and its ad in one go. It starts as a draft, so nothing serves until you make it active.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->statusEffect($config, 'intended_status', 'DRAFT');
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'campaign_id', 'inline_content'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'campaign_id' => Field::dynamic('Campaign', 'linkedin_ads.campaigns', null, null, ['account_id']),
                'name' => Field::text('Name'),
                'inline_content' => Field::json('Post', 'LinkedIn\'s inlineContent object, with post, author, commentary and so on.'),
                'intended_status' => Field::select('Status', ['DRAFT' => 'Draft (serves nothing)', 'ACTIVE' => 'Active (can spend money)'], null, 'DRAFT'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $account = $this->numericId($config, 'account_id', 'The ad account');
        $creative = array_filter(['campaign' => $this->urn($config, 'campaign_id', 'The campaign', 'sponsoredCampaign'), 'name' => $config['name'] ?? null, 'inlineContent' => $this->jsonSetting($config, 'inline_content', 'The post', true), 'intendedStatus' => strtoupper((string) ($config['intended_status'] ?? 'DRAFT'))]);

        $body = $this->linkedin($run, $config, 'POST', "/adAccounts/{$account}/creatives", 'action=createInline', ['creative' => $creative]);

        return ['created' => true, 'id' => data_get($body, 'value.creative') ?? data_get($body, 'value'), 'intended_status' => $creative['intendedStatus']];
    }
}
