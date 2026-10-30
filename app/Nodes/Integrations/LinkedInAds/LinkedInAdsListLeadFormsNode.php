<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsListLeadFormsNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_lead_forms';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List Lead Forms';
    }

    public function description(): string
    {
        return 'Lists the lead forms of an ad account or company page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->formOwnerFields(),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
                'start' => Field::advanced(Field::integer('Start at', null, 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = 'q=owner&owner='.$this->formOwner($config).'&count='.max(1, min(100, (int) ($config['limit'] ?? 25))).'&start='.max(0, (int) ($config['start'] ?? 0));

        return $this->indexedElements($this->linkedin($run, $config, 'GET', '/leadForms', $query), 'forms');
    }
}
