<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsRankTrackerCompetitorsStatsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_rank_tracker_competitors_stats';
    }

    public function name(): string
    {
        return 'Ahrefs: Rank Tracker Competitors Stats';
    }

    public function description(): string
    {
        return 'Share of voice, traffic and positions of a project\'s competitors.';
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
                'date' => Field::text('Date', 'A date to report metrics on in YYYY-MM-DD format.'),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'competitor,share_of_voice,share_of_traffic_value,traffic,traffic_value,average_position,pos_1_3,pos_4_10,pos_11_20,pos_21_50,pos_51_plus,pos_no_rank')),
                'volume_mode' => Field::advanced(Field::select('Volume mode', ['monthly', 'average'], 'The search volume calculation mode: monthly or average.', 'monthly')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/rank-tracker/competitors-stats',
            'items' => 'competitors-metrics',
            'select' => 'competitor,share_of_voice,share_of_traffic_value,traffic,traffic_value,average_position,pos_1_3,pos_4_10,pos_11_20,pos_21_50,pos_51_plus,pos_no_rank',
            'query' => ['project_id', 'device', 'date', 'select', 'volume_mode'],
            'dated' => true,
        ];
    }
}
