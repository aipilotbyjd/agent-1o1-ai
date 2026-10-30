<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsPreviewCreativeNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_preview_creative';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Preview Creative';
    }

    public function description(): string
    {
        return 'Gets an HTML preview of how a creative looks.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = 'q=creative&creative='.rawurlencode($this->urn($config, 'creative_id', 'The creative', 'sponsoredCreative')).'&account='.rawurlencode($this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount'));

        return $this->elements($this->linkedin($run, $config, 'GET', '/adPreviews', $query), 'previews');
    }
}
