<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsSiteAuditPageExplorerNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_site_audit_page_explorer';
    }

    public function name(): string
    {
        return 'Ahrefs: Site Audit Page Explorer';
    }

    public function description(): string
    {
        return 'The pages found by a project\'s crawl, with their metrics.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'date' => Field::text('Date', 'A timestamp in YYYY-MM-DDThh:mm:ss format specifying the crawl date to retrieve metrics from.'),
                'date_compared' => Field::advanced(Field::text('Compare with date', 'A timestamp in YYYY-MM-DDThh:mm:ss format specifying the crawl date to compare metrics with.')),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'url,http_code,title,depth,content_nr_word,backlinks,compliant')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'issue_id' => Field::advanced(Field::text('Issue id', 'The unique identifier of an issue.')),
                'filter_mode' => Field::advanced(Field::select('Filter mode', ['added', 'new', 'removed', 'missing', 'no_change'], 'Indicates which pages to return compared to the previous crawl.', null)),
                'offset' => Field::advanced(Field::integer('Offset', 'The number of results to skip.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-audit/page-explorer',
            'items' => 'pages',
            'select' => 'url,http_code,title,depth,content_nr_word,backlinks,compliant',
            'query' => ['project_id', 'date', 'date_compared', 'limit', 'select', 'where', 'order_by', 'issue_id', 'filter_mode', 'offset'],
            'limited' => true,
        ];
    }
}
