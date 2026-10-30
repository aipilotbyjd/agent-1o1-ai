<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsMetricsByCountryNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_metrics_by_country';
    }

    public function name(): string
    {
        return 'Ahrefs: Metrics by Country';
    }

    public function description(): string
    {
        return 'Organic and paid traffic, keywords and cost of a domain or URL by country.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'The target of the search: a domain or a URL.'),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'date' => Field::text('Date', 'A date to report metrics on in YYYY-MM-DD format.'),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'country,org_cost,org_keywords,org_keywords_1_3,org_traffic,paid_cost,paid_keywords,paid_pages,paid_traffic')),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the target.', 'subdomains')),
                'portfolio_id' => Field::advanced(Field::integer('Portfolio id', 'The unique identifier of the portfolio.')),
                'protocol' => Field::advanced(Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', 'both')),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode: monthly or average.', 'monthly')),
                'traffic_mode' => Field::advanced(Field::select('Traffic mode', ['static', 'adaptive'], 'The organic traffic calculation mode: static or adaptive.', 'adaptive')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/metrics-by-country',
            'items' => 'metrics',
            'select' => 'country,org_cost,org_keywords,org_keywords_1_3,org_traffic,paid_cost,paid_keywords,paid_pages,paid_traffic',
            'query' => ['target', 'limit', 'date', 'select', 'mode', 'portfolio_id', 'protocol', 'volume_mode', 'traffic_mode'],
            'dated' => true,
            'limited' => true,
        ];
    }
}
