<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleCalendarListEventsNode extends AbstractGoogleCalendarNode
{
    public function type(): string
    {
        return 'google_calendar_list_events';
    }

    public function name(): string
    {
        return 'Google Calendar: List Events';
    }

    public function description(): string
    {
        return 'Lists upcoming events on a calendar.';
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
                'calendar_id' => Field::dynamic('Calendar', 'google_calendar.calendars', 'Defaults to your primary calendar.', 'primary'),
                'time_min' => Field::datetime('From', 'Only events ending after this time. Defaults to now.'),
                'max_results' => Field::integer('Max results', null, 10, 1, 2500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $calendarId = $config['calendar_id'] ?? 'primary';

        return $this->get($run, '/calendars/'.rawurlencode($calendarId).'/events', $config, [
            'timeMin' => $config['time_min'] ?? now()->toRfc3339String(),
            'maxResults' => $config['max_results'] ?? 10,
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
        ]);
    }
}
