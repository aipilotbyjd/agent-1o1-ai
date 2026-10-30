<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsGetLeadFormNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_get_lead_form';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Get Lead Form';
    }

    public function description(): string
    {
        return 'Reads one lead form with its questions.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => Field::text('Form id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->linkedin($run, $config, 'GET', '/leadForms/'.$this->numericId($config, 'form_id', 'The form'));
    }
}
