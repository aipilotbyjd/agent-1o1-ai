<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsCrawledPagesNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_crawled_pages';
    }

    public function name(): string
    {
        return 'Ahrefs: Crawled Pages';
    }

    public function description(): string
    {
        return 'The pages of a domain or URL that Ahrefs has crawled.';
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
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'first_seen,http_code,languages,last_attempt,last_crawled,page_category,page_type,publish_date,title,url,url_rating')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the target.', 'subdomains')),
                'protocol' => Field::advanced(Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', 'both')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/crawled-pages',
            'items' => 'pages',
            'select' => 'first_seen,http_code,languages,last_attempt,last_crawled,page_category,page_type,publish_date,title,url,url_rating',
            'query' => ['target', 'limit', 'select', 'order_by', 'where', 'mode', 'protocol'],
            'limited' => true,
        ];
    }
}
