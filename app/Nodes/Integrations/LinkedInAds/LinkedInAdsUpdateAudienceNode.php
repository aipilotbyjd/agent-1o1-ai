<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsUpdateAudienceNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_update_audience';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Update Audience';
    }

    public function description(): string
    {
        return 'Renames an audience or changes its description.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'name' => Field::text('Name'),
                'description' => Field::text('Description'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'segment_id', 'The audience');

        $this->patch($run, $config, "/dmpSegments/{$id}", $this->only($config, ['name', 'description']));

        return ['updated' => true, 'id' => $id];
    }
}
