<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsOutgoingInternalAnchorsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_outgoing_internal_anchors';
    }

    public function name(): string
    {
        return 'Ahrefs: Outgoing Internal Anchors';
    }

    public function description(): string
    {
        return 'The anchor texts of the internal links a domain or URL makes.';
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
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'anchor,dofollow_links,first_seen,linked_pages,links_from_target')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
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
            'path' => '/site-explorer/linked-anchors-internal',
            'items' => 'linkedanchors',
            'select' => 'anchor,dofollow_links,first_seen,linked_pages,links_from_target',
            'query' => ['target', 'limit', 'select', 'where', 'order_by', 'mode', 'portfolio_id', 'protocol'],
            'limited' => true,
        ];
    }
}
