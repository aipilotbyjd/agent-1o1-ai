<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsAnchorsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_anchors';
    }

    public function name(): string
    {
        return 'Ahrefs: Anchors';
    }

    public function description(): string
    {
        return 'The anchor texts of the links to a domain or URL.';
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
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'anchor,dofollow_links,first_seen,is_spam,last_seen,links_to_target,lost_links,new_links,refdomains,refpages,top_domain_rating')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'history' => Field::advanced(Field::text('History', 'A time frame to add lost backlinks to the report.')),
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
            'path' => '/site-explorer/anchors',
            'items' => 'anchors',
            'select' => 'anchor,dofollow_links,first_seen,is_spam,last_seen,links_to_target,lost_links,new_links,refdomains,refpages,top_domain_rating',
            'query' => ['target', 'limit', 'select', 'where', 'order_by', 'history', 'mode', 'portfolio_id', 'protocol'],
            'limited' => true,
        ];
    }
}
