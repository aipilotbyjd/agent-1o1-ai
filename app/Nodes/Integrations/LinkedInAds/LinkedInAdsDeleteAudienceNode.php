<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsDeleteAudienceNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_delete_audience';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Delete Audience';
    }

    public function description(): string
    {
        return 'Deletes an audience. Campaigns that target it stop reaching it. This cannot be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['segment_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'segment_id' => Field::dynamic('Audience', 'linkedin_ads.segments', null, null, ['account_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'segment_id', 'The audience');

        $this->linkedin($run, $config, 'DELETE', "/dmpSegments/{$id}");

        return ['deleted' => true, 'id' => $id];
    }
}
