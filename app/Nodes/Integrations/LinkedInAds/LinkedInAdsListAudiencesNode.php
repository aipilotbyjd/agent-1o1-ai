<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsListAudiencesNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_list_audiences';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List Audiences';
    }

    public function description(): string
    {
        return 'Lists the audiences of an ad account.';
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
                'limit' => Field::integer('Max results', null, 25, 1, 100),
                'start' => Field::advanced(Field::integer('Start at', null, 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = 'q=account&account='.rawurlencode($this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount')).'&count='.max(1, min(100, (int) ($config['limit'] ?? 25))).'&start='.max(0, (int) ($config['start'] ?? 0));

        return $this->indexedElements($this->linkedin($run, $config, 'GET', '/dmpSegments', $query), 'audiences');
    }
}
