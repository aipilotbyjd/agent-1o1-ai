<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsRankTrackerOverviewNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_rank_tracker_overview';
    }

    public function name(): string
    {
        return 'Ahrefs: Rank Tracker Overview';
    }

    public function description(): string
    {
        return 'The tracked keywords of a project with their positions.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'device'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'device' => Field::select('Device', ['desktop', 'mobile'], 'Choose between mobile and desktop rankings.', null),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
                'date' => Field::text('Date', 'A date to report metrics on in YYYY-MM-DD format.'),
                'date_compared' => Field::advanced(Field::text('Compare with date', 'A date to compare metrics with in YYYY-MM-DD format.')),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'keyword,position,position_diff,position_prev,url,volume,keyword_difficulty,traffic,traffic_diff,location,country,language,tags,serp_updated')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode: monthly or average.', 'monthly')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/rank-tracker/overview',
            'items' => 'overviews',
            'select' => 'keyword,position,position_diff,position_prev,url,volume,keyword_difficulty,traffic,traffic_diff,location,country,language,tags,serp_updated',
            'query' => ['project_id', 'device', 'limit', 'date', 'date_compared', 'select', 'where', 'order_by', 'volume_mode'],
            'dated' => true,
            'limited' => true,
        ];
    }
}
