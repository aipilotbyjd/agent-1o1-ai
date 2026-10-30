<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsMetricsHistoryNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_metrics_history';
    }

    public function name(): string
    {
        return 'Ahrefs: Metrics History';
    }

    public function description(): string
    {
        return 'Organic and paid traffic and cost of a domain or URL over time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'date_from'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'The target of the search: a domain or a URL.'),
                'date_from' => Field::text('From', 'The start date of the historical period in YYYY-MM-DD format.'),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'date,org_cost,org_traffic,paid_cost,paid_traffic')),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the target.', 'subdomains')),
                'portfolio_id' => Field::advanced(Field::integer('Portfolio id', 'The unique identifier of the portfolio.')),
                'protocol' => Field::advanced(Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', 'both')),
                'date_to' => Field::text('To', 'The end date of the historical period in YYYY-MM-DD format.'),
                'history_grouping' => Field::advanced(Field::select('History grouping', ['daily', 'weekly', 'monthly'], 'The time interval used to group historical data.', 'monthly')),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode: monthly or average.', 'monthly')),
                'traffic_mode' => Field::advanced(Field::select('Traffic mode', ['static', 'adaptive'], 'The organic traffic calculation mode: static or adaptive.', 'adaptive')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/metrics-history',
            'items' => 'metrics',
            'select' => 'date,org_cost,org_traffic,paid_cost,paid_traffic',
            'query' => ['target', 'date_from', 'country', 'select', 'mode', 'portfolio_id', 'protocol', 'date_to', 'history_grouping', 'volume_mode', 'traffic_mode'],
        ];
    }
}
