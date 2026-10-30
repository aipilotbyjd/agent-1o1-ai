<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinkedInAdsGetStatisticsNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_get_statistics';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Create Report by Several Breakdowns';
    }

    public function description(): string
    {
        return 'Reports the same numbers broken down up to three ways at once.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pivots'],
            'properties' => [
                ...$this->credentialFields(),
                'pivots' => Field::list('Break down by', 'Up to 3, like CAMPAIGN and CREATIVE.'),
                'account_ids' => Field::list('Ad account ids'),
                'campaign_group_ids' => Field::list('Campaign group ids'),
                'campaign_ids' => Field::list('Campaign ids'),
                'creative_ids' => Field::advanced(Field::list('Creative ids')),
                'date_from' => Field::text('From', 'YYYY-MM-DD. Empty is 30 days ago.'),
                'date_to' => Field::text('To', 'YYYY-MM-DD. Empty is today.'),
                'time_granularity' => Field::select('Group by time', ['ALL' => 'Whole range', 'DAILY' => 'Day', 'MONTHLY' => 'Month', 'YEARLY' => 'Year'], null, 'ALL'),
                'fields' => Field::list('Metrics', 'Up to 20. Empty reads impressions, clicks, costInLocalCurrency, landingPageClicks, externalWebsiteConversions, oneClickLeads, likes, comments, shares and follows.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $pivots = array_slice($this->stringList($config, 'pivots'), 0, 3);

        if ($pivots === []) {
            throw new InvalidArgumentException('Give at least one breakdown.');
        }

        return $this->elements($this->linkedin($run, $config, 'GET', '/adAnalytics', 'q=statistics&pivots='.self::restliList($pivots).$this->analyticsQuery($config)), 'rows');
    }
}
