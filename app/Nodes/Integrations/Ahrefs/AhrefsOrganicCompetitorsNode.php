<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsOrganicCompetitorsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_organic_competitors';
    }

    public function name(): string
    {
        return 'Ahrefs: Organic Competitors';
    }

    public function description(): string
    {
        return 'The organic search competitors of a domain or URL.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'country'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'The target of the search: a domain or a URL.'),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'date' => Field::text('Date', 'A date to report metrics on in YYYY-MM-DD format.'),
                'date_compared' => Field::advanced(Field::text('Compare with date', 'A date to compare metrics with in YYYY-MM-DD format.')),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'competitor_domain,competitor_url,domain_rating,keywords_common,keywords_competitor,keywords_target,share,traffic,pages,value')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode: monthly or average.', 'monthly')),
                'traffic_mode' => Field::advanced(Field::select('Traffic mode', ['static', 'adaptive'], 'The organic traffic calculation mode: static or adaptive.', 'adaptive')),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the search based on the target you entered.', 'subdomains')),
                'portfolio_id' => Field::advanced(Field::integer('Portfolio id', 'The unique identifier of the portfolio.')),
                'protocol' => Field::advanced(Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', 'both')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/organic-competitors',
            'items' => 'competitors',
            'select' => 'competitor_domain,competitor_url,domain_rating,keywords_common,keywords_competitor,keywords_target,share,traffic,pages,value',
            'query' => ['target', 'country', 'limit', 'date', 'date_compared', 'select', 'where', 'order_by', 'volume_mode', 'traffic_mode', 'mode', 'portfolio_id', 'protocol'],
            'dated' => true,
            'limited' => true,
        ];
    }
}
