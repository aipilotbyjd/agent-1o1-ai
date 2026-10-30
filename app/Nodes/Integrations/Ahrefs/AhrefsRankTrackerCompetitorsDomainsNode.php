<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsRankTrackerCompetitorsDomainsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_rank_tracker_competitors_domains';
    }

    public function name(): string
    {
        return 'Ahrefs: Rank Tracker Competitors Domains';
    }

    public function description(): string
    {
        return 'The competitor domains ranking for a project\'s tracked keywords.';
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
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'domain,keywords,pages,share_of_traffic_value,share_of_traffic_value_prev,share_of_voice,share_of_voice_prev,status,traffic,traffic_prev,traffic_value,traffic_value_prev')),
                'where' => Field::advanced(Field::text('Filter', 'A filter in Ahrefs\' syntax, such as {"field":"domain_rating_source","is":["gte",50]}.', '{"field":"domain_rating_source","is":["gte",50]}')),
                'order_by' => Field::advanced(Field::text('Sort', 'Columns with optional direction, such as traffic:desc.', 'traffic:desc')),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode: monthly or average.', 'monthly')),
                'target_and_tracked_competitors_only' => Field::advanced(Field::boolean('Target and tracked competitors only', 'Restrict domains to target and tracked competitors', false)),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/rank-tracker/competitors-domains',
            'items' => 'competitors-domains',
            'select' => 'domain,keywords,pages,share_of_traffic_value,share_of_traffic_value_prev,share_of_voice,share_of_voice_prev,status,traffic,traffic_prev,traffic_value,traffic_value_prev',
            'query' => ['project_id', 'device', 'limit', 'date', 'date_compared', 'select', 'where', 'order_by', 'volume_mode', 'target_and_tracked_competitors_only'],
            'dated' => true,
            'limited' => true,
        ];
    }
}
