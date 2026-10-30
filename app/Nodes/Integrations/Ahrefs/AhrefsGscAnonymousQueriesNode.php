<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsGscAnonymousQueriesNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_gsc_anonymous_queries';
    }

    public function name(): string
    {
        return 'Ahrefs: GSC Anonymous Queries';
    }

    public function description(): string
    {
        return 'Keywords Ahrefs estimates behind the queries Google Search Console hides.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'date_from', 'country'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'date_from' => Field::text('From', 'The start date of the historical period in YYYY-MM-DD format.'),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'cpc,keyword,keyword_country,keyword_difficulty,position,position_kind,traffic,url,volume')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/gsc/anonymous-queries',
            'items' => 'keywords',
            'select' => 'cpc,keyword,keyword_country,keyword_difficulty,position,position_kind,traffic,url,volume',
            'query' => ['project_id', 'date_from', 'country', 'limit', 'select', 'where', 'order_by'],
            'limited' => true,
        ];
    }
}
