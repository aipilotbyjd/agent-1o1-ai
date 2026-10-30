<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsStartInsightsReportNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_start_insights_report';
    }

    public function name(): string
    {
        return 'Meta Ads: Start Insights Report';
    }

    public function description(): string
    {
        return 'Starts a large insights report in the background, which is the way to read big date ranges. Read it with Get Insights Report.';
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
                'campaign_id' => Field::dynamic('Only this campaign', 'meta_ads.campaigns', null, null, ['account_id']),
                'ad_set_id' => Field::dynamic('Only this ad set', 'meta_ads.ad_sets', null, null, ['account_id']),
                'ad_id' => Field::dynamic('Only this ad', 'meta_ads.ads', null, null, ['account_id']),
                'level' => Field::select('Rows per', ['account', 'campaign', 'adset', 'ad'], null, 'campaign'),
                'fields' => Field::list('Metrics', 'Empty reads spend, impressions, reach, clicks, ctr, cpc, cpm, actions, plus the names of the rows.'),
                'date_preset' => Field::select('Period', ['today', 'yesterday', 'this_month', 'last_month', 'this_quarter', 'maximum', 'last_3d', 'last_7d', 'last_14d', 'last_28d', 'last_30d', 'last_90d', 'last_week_mon_sun', 'last_quarter', 'last_year', 'this_year'], null, 'last_30d'),
                'since' => Field::text('From', 'YYYY-MM-DD. Used instead of the period, with To.'),
                'until' => Field::text('To', 'YYYY-MM-DD.'),
                'time_increment' => Field::advanced(Field::text('Rows per days', 'A number from 1 to 90, monthly or all_days.')),
                'breakdowns' => Field::advanced(Field::list('Breakdowns', 'Like age, gender, country, publisher_platform or device_platform.')),
                'action_breakdowns' => Field::advanced(Field::list('Action breakdowns', 'Like action_type or action_device.')),
                'action_attribution_windows' => Field::advanced(Field::list('Attribution windows', 'Like 7d_click or 1d_view.')),
                'filtering' => Field::advanced(Field::json('Filters', 'A list of field, operator and value.', 'array')),
                'sort' => Field::advanced(Field::text('Sort by', 'A field and _ascending or _descending, like reach_descending.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->graph($run, $config, 'POST', '/'.$this->insightsObject($config).'/insights', $this->insightsParams($config));

        return ['report_run_id' => isset($body['report_run_id']) ? (string) $body['report_run_id'] : null];
    }
}
