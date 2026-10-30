<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsSiteAuditPageContentNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_site_audit_page_content';
    }

    public function name(): string
    {
        return 'Ahrefs: Site Audit Page Content';
    }

    public function description(): string
    {
        return 'The text and HTML of a page from a project\'s crawl.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'target_url'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'target_url' => Field::text('Target url', 'The URL of the page to retrieve content for.'),
                'date' => Field::text('Date', 'A timestamp in YYYY-MM-DDThh:mm:ss format specifying the crawl date to retrieve metrics from.'),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'crawl_datetime,page_text_md')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-audit/page-content',
            'items' => 'page-content',
            'select' => 'crawl_datetime,page_text_md',
            'query' => ['project_id', 'target_url', 'date', 'select'],
        ];
    }
}
