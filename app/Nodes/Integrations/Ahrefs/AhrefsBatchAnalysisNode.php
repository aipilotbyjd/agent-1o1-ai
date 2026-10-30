<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsBatchAnalysisNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_batch_analysis';
    }

    public function name(): string
    {
        return 'Ahrefs: Batch Analysis';
    }

    public function description(): string
    {
        return 'SEO metrics for up to 100 URLs or domains at once.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['targets'],
            'properties' => [
                ...$this->credentialFields(),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'url,mode,protocol,domain_rating,url_rating,ahrefs_rank,backlinks,refdomains,org_keywords,org_traffic,org_cost,paid_keywords,paid_traffic')),
                'order_by' => Field::advanced(Field::list('Sort', 'A list of fields to order the results by, with optional direction.')),
                'targets' => Field::json('Targets', 'A list of targets to do batch analysis. Such as [{"url":"https://www.ahrefs.com","mode":"exact","protocol":"both"}].', 'array'),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode: monthly or average.', 'monthly')),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'top_countries' => Field::advanced(Field::integer('Top countries', 'The number of countries to return in the org_traffic_top_by_country field.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/batch-analysis/batch-analysis',
            'items' => 'targets',
            'select' => 'url,mode,protocol,domain_rating,url_rating,ahrefs_rank,backlinks,refdomains,org_keywords,org_traffic,org_cost,paid_keywords,paid_traffic',
            'body' => ['select', 'order_by', 'targets', 'volume_mode', 'country', 'top_countries'],
            'json' => ['targets'],
            'lists' => ['select', 'order_by'],
            'integers' => ['top_countries'],
        ];
    }
}
