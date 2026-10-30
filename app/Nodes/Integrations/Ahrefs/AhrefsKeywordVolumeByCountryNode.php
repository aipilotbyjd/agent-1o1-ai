<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsKeywordVolumeByCountryNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_keyword_volume_by_country';
    }

    public function name(): string
    {
        return 'Ahrefs: Keyword Volume by Country';
    }

    public function description(): string
    {
        return 'The search volume of a keyword in each country.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['keyword'],
            'properties' => [
                ...$this->credentialFields(),
                'keyword' => Field::text('Keyword', 'The keyword to show metrics for.'),
                'limit' => Field::integer('Limit', 'The most rows to return. Ahrefs charges per row.', 100, 1, 100000),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/keywords-explorer/volume-by-country',
            'items' => 'countries',
            'query' => ['keyword', 'limit'],
            'limited' => true,
        ];
    }
}
