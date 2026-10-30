<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsListLocationsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_list_locations';
    }

    public function name(): string
    {
        return 'Ahrefs: List Locations';
    }

    public function description(): string
    {
        return 'The locations and languages Rank Tracker can track in a country.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['country_code'],
            'properties' => [
                ...$this->credentialFields(),
                'country_code' => Field::text('Country code', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'us_state' => Field::advanced(Field::text('Us state', 'A two-letter US state code (ISO 3166-2:US).')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/management/locations',
            'items' => 'location',
            'query' => ['country_code', 'us_state'],
        ];
    }
}
