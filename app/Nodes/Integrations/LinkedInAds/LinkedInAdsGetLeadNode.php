<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsGetLeadNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_get_lead';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Get Lead Form Response';
    }

    public function description(): string
    {
        return 'Reads one lead by its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['lead_id'],
            'properties' => [
                ...$this->credentialFields(),
                'lead_id' => Field::text('Lead id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['lead' => $this->shapeLead($this->linkedin($run, $config, 'GET', '/leadFormResponses/'.$this->plainId($config, 'lead_id', 'The lead id')))];
    }
}
