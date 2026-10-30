<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsRankTrackerCompetitorsPagesNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_rank_tracker_competitors_pages';
    }

    public function name(): string
    {
        return 'Ahrefs: Rank Tracker Competitors Pages';
    }

    public function description(): string
    {
        return 'The competitor pages ranking for a project\'s tracked keywords.';
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
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'keywords,share_of_traffic_value,share_of_traffic_value_prev,share_of_voice,share_of_voice_prev,status,title,title_prev,traffic,traffic_prev,traffic_value,traffic_value_prev,url')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode: monthly or average.', 'monthly')),
                'target_and_tracked_competitors_only' => Field::advanced(Field::boolean('Target and tracked competitors only', 'Restrict pages to target and tracked competitors', false)),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/rank-tracker/competitors-pages',
            'items' => 'competitors-pages',
            'select' => 'keywords,share_of_traffic_value,share_of_traffic_value_prev,share_of_voice,share_of_voice_prev,status,title,title_prev,traffic,traffic_prev,traffic_value,traffic_value_prev,url',
            'query' => ['project_id', 'device', 'limit', 'date', 'date_compared', 'select', 'where', 'order_by', 'volume_mode', 'target_and_tracked_competitors_only'],
            'dated' => true,
            'limited' => true,
        ];
    }
}
