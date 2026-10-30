<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsRankTrackerSerpOverviewNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_rank_tracker_serp_overview';
    }

    public function name(): string
    {
        return 'Ahrefs: Rank Tracker SERP Overview';
    }

    public function description(): string
    {
        return 'The search results of a tracked keyword.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'keyword', 'country', 'device'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'keyword' => Field::text('Keyword', 'The keyword to return SERP Overview for.'),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'device' => Field::select('Device', ['desktop', 'mobile'], 'Choose between mobile and desktop rankings.', null),
                'date' => Field::text('Date', 'A timestamp on which the last available SERP Overview is returned in YYYY-MM-DDThh:mm:ss format.'),
                'language_code' => Field::advanced(Field::text('Language code', 'The language code of a tracked keyword.You can use the management/project-keywords endpoint to get country codes, language codes and location IDs for your tracked keyword')),
                'location_id' => Field::advanced(Field::integer('Location id', 'The location ID of a tracked keyword.You can use the management/project-keywords endpoint to get country codes, language codes and location IDs for your tracked keywords.')),
                'top_positions' => Field::advanced(Field::integer('Top positions', 'The number of top organic SERP positions to return.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/rank-tracker/serp-overview',
            'items' => 'positions',
            'query' => ['project_id', 'keyword', 'country', 'device', 'date', 'language_code', 'location_id', 'top_positions'],
        ];
    }
}
