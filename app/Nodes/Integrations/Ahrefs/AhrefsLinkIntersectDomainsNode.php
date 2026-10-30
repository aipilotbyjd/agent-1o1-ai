<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsLinkIntersectDomainsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_link_intersect_domains';
    }

    public function name(): string
    {
        return 'Ahrefs: Link Intersect: Referring Domains';
    }

    public function description(): string
    {
        return 'The domains that link to your competitors, and whether they link to you.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'competitors'],
            'properties' => [
                ...$this->credentialFields(),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'domain,domain_rating,intersect,links_to_target')),
                'where' => Field::advanced(Field::json('Filter', 'The filter expression.')),
                'order_by' => Field::advanced(Field::list('Sort', 'A list of columns to order results by, with optional direction.')),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'target' => Field::json('Target', 'The target to compare. Such as {"url":"example.com","mode":"exact","protocol":"both"}.'),
                'competitors' => Field::json('Competitors', 'The competitors whose backlinks are compared with the target, in output column order. Such as [{"url":"example.com","mode":"exact","protocol":"both"}].', 'array'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/competitive-analysis/link-intersect-refdomains',
            'items' => 'referring-domains',
            'select' => 'domain,domain_rating,intersect,links_to_target',
            'body' => ['select', 'where', 'order_by', 'limit', 'target', 'competitors'],
            'json' => ['where', 'target', 'competitors'],
            'lists' => ['select', 'order_by'],
            'integers' => ['limit'],
            'limited' => true,
        ];
    }
}
