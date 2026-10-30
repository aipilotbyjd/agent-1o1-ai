<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushSearchTrackingLocationsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_search_tracking_locations';
    }

    public function name(): string
    {
        return 'Semrush: Search Tracking Locations';
    }

    public function description(): string
    {
        return 'Finds Position Tracking locations by id, name, country or type.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'location_id' => Field::integer('Location id'),
                'name' => Field::text('Name', null, 'London'),
                'country_code' => Field::text('Country', 'A two-letter code.', 'GB'),
                'type' => Field::text('Type', 'Such as city, country or postal code.', 'city'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->semrushJson($run, $config, 'GET', '/position-tracking/management/v1/info/locations', ['location_id' => $config['location_id'] ?? null, 'name' => $config['name'] ?? null, 'country_code' => $config['country_code'] ?? null, 'type' => $config['type'] ?? null]);
        $locations = array_is_list($answer) ? $answer : (array) ($answer['locations'] ?? $answer['data'] ?? $answer);

        return ['locations' => array_values($locations), 'count' => count($locations)];
    }
}
