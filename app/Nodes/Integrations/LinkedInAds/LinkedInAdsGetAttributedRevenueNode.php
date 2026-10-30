<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsGetAttributedRevenueNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_get_attributed_revenue';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Get Attributed Revenue';
    }

    public function description(): string
    {
        return 'Reports the revenue LinkedIn attributes to an account\'s campaigns over the last year at most.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'pivots', 'date_from'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'pivots' => Field::list('Break down by', 'Up to 3 of CAMPAIGN, CAMPAIGN_GROUP and ACCOUNT.'),
                'date_from' => Field::text('From', 'YYYY-MM-DD, within the last year.'),
                'date_to' => Field::text('To', 'YYYY-MM-DD. Empty is today.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = 'q=attributedRevenueMetrics&fields=revenueAttributionMetrics,dateRange,pivotValues&dateRange='.$this->dateRange($config).'&account='.rawurlencode($this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount')).'&pivots='.self::restliList(array_slice($this->stringList($config, 'pivots'), 0, 3));

        return $this->elements($this->linkedin($run, $config, 'GET', '/adAnalytics', $query), 'rows');
    }
}
