<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsKeywordVolumeHistoryNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_keyword_volume_history';
    }

    public function name(): string
    {
        return 'Ahrefs: Keyword Volume History';
    }

    public function description(): string
    {
        return 'The search volume of a keyword over time.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['keyword', 'country'],
            'properties' => [
                ...$this->credentialFields(),
                'keyword' => Field::text('Keyword', 'The keyword to show metrics for.'),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'date_from' => Field::text('From', 'The start date of the historical period in YYYY-MM-DD format.'),
                'date_to' => Field::text('To', 'The end date of the historical period in YYYY-MM-DD format.'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/keywords-explorer/volume-history',
            'items' => 'metrics',
            'query' => ['keyword', 'country', 'date_from', 'date_to'],
        ];
    }
}
